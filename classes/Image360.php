<?php

define('_PS_360_IMG_DIR_', _PS_IMG_DIR_ . '360/');
define('_THEME_360_DIR_', _PS_IMG_ . '360/');

class Image360 extends ObjectModel
{
    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'image360',
        'primary' => 'id_image',
        'multilang' => false,
        'fields' => array(
            'id_product' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true),
            'position' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'cover' => array('type' => self::TYPE_BOOL, 'validate' => 'isBool'),
        ),
    );
    /** @var int access rights of created folders (octal) */
    protected static $access_rights = 0775;
    protected static $_cacheGetSize = array();
    public $id;
    /** @var integer Image ID */
    public $id_image;
    /** @var integer Product ID */
    public $id_product;
    /** @var integer Position used to order images of the same product */
    public $position;
    /** @var boolean Image is cover */
    public $cover;
    /** @var string image extension */
    public $image_format = 'jpg';
    /** @var string path to index.php file to be copied to new image folders */
    public $source_index;
    /** @var string image folder */
    protected $folder;
    /** @var string image path without extension */
    protected $existing_path;

    public function __construct($id = null, $id_lang = null)
    {
        parent::__construct($id, $id_lang);
        $this->image_dir = _PS_360_IMG_DIR_;
        $this->source_index = _PS_360_IMG_DIR_ . 'index.php';
    }

    /**
     * Return available images for a 360° view
     *
     * @param integer $id_product Product ID
     * @return array Images
     */
    public static function getImages($id_product)
    {
        $sql = 'SELECT *
			FROM ' . _DB_PREFIX_ . 'image360
			WHERE id_product = ' . (int)$id_product . ' ORDER BY position ASC';
        return Db::getInstance()->executeS($sql);
    }

    /**
     * Return Images
     *
     * @return array Images
     */
    public static function getAllImages()
    {
        return Db::getInstance()->executeS('
		SELECT id_image, id_product
		FROM ' . _DB_PREFIX_ . 'image360
		ORDER BY id_image ASC');
    }

    /**
     * Return number of images for a product
     *
     * @param integer $id_product Product ID
     * @return integer number of images
     */
    public static function getImagesTotal($id_product)
    {
        $result = Db::getInstance()->getRow('
		SELECT COUNT(id_image) AS total
		FROM ' . _DB_PREFIX_ . 'image360
		WHERE id_product = ' . (int)$id_product);
        return $result['total'];
    }

    /**
     * Delete 360° view cover
     *
     * @param integer $id_product Product ID
     * @return boolean result
     */
    public static function deleteCover($id_product)
    {
        if (!Validate::isUnsignedId($id_product))
            die(Tools::displayError());

        return (Db::getInstance()->execute('
			UPDATE ' . _DB_PREFIX_ . 'image360
			SET cover = 0
			WHERE id_product = ' . (int)$id_product
        ));
    }

    /**
     * Get 360° view cover
     *
     * @param integer $id_product Product ID
     * @return boolean result
     */
    public static function getCover($id_product)
    {
        return Db::getInstance()->getRow('
			SELECT * FROM ' . _DB_PREFIX_ . 'image360 i
			WHERE id_product = ' . (int)$id_product . '
			AND cover= 1');
    }

    public static function getWidth($params, &$smarty)
    {
        $result = self::getSize($params['type']);
        return $result['width'];
    }

    public static function getSize($type)
    {
        if (!isset(self::$_cacheGetSize[$type]) || self::$_cacheGetSize[$type] === null)
            self::$_cacheGetSize[$type] = Db::getInstance()->getRow('
				SELECT width, height
				FROM ' . _DB_PREFIX_ . 'image_type
				WHERE name = \'' . pSQL($type) . '\'
			');
        return self::$_cacheGetSize[$type];
    }

    public static function getHeight($params, &$smarty)
    {
        $result = self::getSize($params['type']);
        return $result['height'];
    }

    /**
     * Recursively deletes all 360° view images in the given folder tree and removes empty folders.
     *
     * @param string $path folder containing the product images to delete
     * @param string $format image format
     * @return bool success
     */
    public static function deleteAllImages($path, $format = 'jpg')
    {
        if (!$path || !$format || !is_dir($path))
            return false;
        foreach (scandir($path) as $file) {
            if (preg_match('/^[0-9]+(\-(.*))?\.' . $format . '$/', $file))
                unlink($path . $file);
            else if (is_dir($path . $file) && (preg_match('/^[0-9]$/', $file)))
                Image360::deleteAllImages($path . $file . '/', $format);
        }

        // Can we remove the image folder?
        if (is_numeric(basename($path))) {
            $remove_folder = true;
            foreach (scandir($path) as $file)
                if (($file != '.' && $file != '..' && $file != 'index.php')) {
                    $remove_folder = false;
                    break;
                }

            if ($remove_folder) {
                // we're only removing index.php if it's a folder we want to delete
                if (file_exists($path . 'index.php'))
                    @unlink($path . 'index.php');
                @rmdir($path);
            }
        }

        return true;
    }

    /**
     * Move all legacy product image files from the image folder root to their subfolder in the new filesystem.
     * If max_execution_time is provided, stops before timeout and returns string "timeout".
     * If any image cannot be moved, stops and returns "false"
     *
     * @param int max_execution_time
     * @return mixed success or timeout
     */
    public static function moveToNewFileSystem($max_execution_time = 0)
    {
        $start_time = time();
        $image = null;
        $tmp_folder = 'duplicates/';
        foreach (scandir(_PS_360_IMG_DIR_) as $file) {
            // matches the base product image or the thumbnails
            if (preg_match('/^([0-9]+\-)([0-9]+)(\-(.*))?\.jpg$/', $file, $matches)) {
                // don't recreate an image object for each image type
                if (!$image || $image->id !== (int)$matches[2])
                    $image = new Image360((int)$matches[2]);
                // image exists in DB and with the correct product?
                if (Validate::isLoadedObject($image) && $image->id_product == (int)rtrim($matches[1], '-')) {
                    // create the new folder if it does not exist
                    if (!$image->createImgFolder())
                        return false;

                    // if there's already a file at the new image path, move it to a dump folder
                    // most likely the preexisting image is a demo image not linked to a product and it's ok to replace it
                    $new_path = _PS_360_IMG_DIR_ . $image->getImgPath() . (isset($matches[3]) ? $matches[3] : '') . '.jpg';
                    if (file_exists($new_path)) {
                        if (!file_exists(_PS_360_IMG_DIR_ . $tmp_folder)) {
                            @mkdir(_PS_360_IMG_DIR_ . $tmp_folder, self::$access_rights);
                            @chmod(_PS_360_IMG_DIR_ . $tmp_folder, self::$access_rights);
                        }
                        $tmp_path = _PS_360_IMG_DIR_ . $tmp_folder . basename($file);
                        if (!@rename($new_path, $tmp_path) || !file_exists($tmp_path))
                            return false;
                    }
                    // move the image
                    if (!@rename(_PS_360_IMG_DIR_ . $file, $new_path) || !file_exists($new_path))
                        return false;
                }
            }
            if ((int)$max_execution_time != 0 && (time() - $start_time > (int)$max_execution_time - 4))
                return 'timeout';
        }
        return true;
    }

    /**
     * Create parent folders for the image in the new filesystem
     *
     * @return bool success
     */
    public function createImgFolder()
    {
        if (!$this->id)
            return false;

        if (!file_exists(_PS_360_IMG_DIR_ . $this->getImgFolder())) {
            // Apparently sometimes mkdir cannot set the rights, and sometimes chmod can't. Trying both.
            $success = @mkdir(_PS_360_IMG_DIR_ . $this->getImgFolder(), self::$access_rights, true);
            $chmod = @chmod(_PS_360_IMG_DIR_ . $this->getImgFolder(), self::$access_rights);

            // Create an index.php file in the new folder
            if (($success || $chmod)
                && !file_exists(_PS_360_IMG_DIR_ . $this->getImgFolder() . 'index.php')
                && file_exists($this->source_index)
            )
                return @copy($this->source_index, _PS_360_IMG_DIR_ . $this->getImgFolder() . 'index.php');
        }
        return true;
    }

    /**
     * Returns the path to the folder containing the image in the new filesystem
     *
     * @return string path to folder
     */
    public function getImgFolder()
    {
        if (!$this->id)
            return false;

        if (!$this->folder)
            $this->folder = Image360::getImgFolderStatic($this->id);

        return $this->folder;
    }

    /**
     * Returns the path to the folder containing the image in the new filesystem
     *
     * @param mixed $id_image
     * @return string path to folder
     */
    public static function getImgFolderStatic($id_image)
    {
        if (!is_numeric($id_image))
            return false;
        $folders = str_split((string)$id_image);
        return implode('/', $folders) . '/';
    }

    /**
     * Returns the path to the image without file extension
     *
     * @return string path
     */
    public function getImgPath()
    {
        if (!$this->id)
            return false;

        $path = $this->getImgFolder() . $this->id;
        return $path;
    }

    /**
     * Try to create and delete some folders to check if moving images to new file system will be possible
     *
     * @return boolean success
     */
    public static function testFileSystem()
    {
        $safe_mode = Tools::getSafeModeStatus();
        if ($safe_mode)
            return false;
        $folder1 = _PS_360_IMG_DIR_ . 'testfilesystem/';
        $test_folder = $folder1 . 'testsubfolder/';
        // check if folders are already existing from previous failed test
        if (file_exists($test_folder)) {
            @rmdir($test_folder);
            @rmdir($folder1);
        }
        if (file_exists($test_folder))
            return false;

        @mkdir($test_folder, self::$access_rights, true);
        @chmod($test_folder, self::$access_rights);
        if (!is_writeable($test_folder))
            return false;
        @rmdir($test_folder);
        @rmdir($folder1);
        if (file_exists($folder1))
            return false;
        return true;
    }

    public function add($autodate = true, $null_values = false)
    {
        if ($this->position <= 0)
            $this->position = Image360::getHighestPosition($this->id_product) + 1;

        return parent::add($autodate, $null_values);
    }

    /**
     * Return highest position of images for a product
     *
     * @param integer $id_product Product ID
     * @return integer highest position of images
     */
    public static function getHighestPosition($id_product)
    {
        $result = Db::getInstance()->getRow('
		SELECT MAX(position) AS max
		FROM ' . _DB_PREFIX_ . 'image360
		WHERE id_product = ' . (int)$id_product);
        return $result['max'];
    }

    public function delete()
    {
        if (!parent::delete())
            return false;

        if (!$this->deleteImage())
            return false;

        // update positions
        $result = Db::getInstance()->executeS('
			SELECT *
			FROM ' . _DB_PREFIX_ . 'image360
			WHERE id_product = ' . (int)$this->id_product . '
			ORDER BY position
		');
        $i = 1;
        if ($result)
            foreach ($result as $row) {
                $row['position'] = $i++;
                Db::getInstance()->update($this->def['table'], $row, 'id_image = ' . (int)$row['id_image'], 1);
            }

        return true;
    }

    /**
     * Delete the product image from disk and remove the containing folder if empty
     * Handles both legacy and new image filesystems
     */
    public function deleteImage($force_delete = false)
    {
        if (!$this->id)
            return false;

        // Delete base image
        if (file_exists($this->image_dir . $this->getExistingImgPath() . '.' . $this->image_format))
            unlink($this->image_dir . $this->getExistingImgPath() . '.' . $this->image_format);
        else
            return false;

        $files_to_delete = array();

        // Delete auto-generated images
        $image_types = ImageType::getImagesTypes();
        foreach ($image_types as $image_type)
            $files_to_delete[] = $this->image_dir . $this->getExistingImgPath() . '-' . $image_type['name'] . '.' . $this->image_format;

        // Delete watermark image
        $files_to_delete[] = $this->image_dir . $this->getExistingImgPath() . '-watermark.' . $this->image_format;
        // delete index.php
        $files_to_delete[] = $this->image_dir . $this->getImgFolder() . 'index.php';

        foreach ($files_to_delete as $file)
            if (file_exists($file) && !@unlink($file))
                return false;

        // Can we delete the image folder?
        if (is_dir($this->image_dir . $this->getImgFolder())) {
            $delete_folder = true;
            foreach (scandir($this->image_dir . $this->getImgFolder()) as $file)
                if (($file != '.' && $file != '..')) {
                    $delete_folder = false;
                    break;
                }
        }
        if (isset($delete_folder) && $delete_folder)
            @rmdir($this->image_dir . $this->getImgFolder());

        return true;
    }

    /**
     * Returns image path in the old or in the new filesystem
     *
     * @ returns string image path
     */
    public function getExistingImgPath()
    {
        if (!$this->id)
            return false;

        if (!$this->existing_path) {
            if (Configuration::get('PS_LEGACY_IMAGES') && file_exists(_PS_360_IMG_DIR_ . '360-' . $this->id_product . '-' . $this->id . '.' . $this->image_format))
                $this->existing_path = '360-' . $this->id_product . '-' . $this->id;
            else
                $this->existing_path = $this->getImgPath();
        }

        return $this->existing_path;
    }

    /**
     * Reposition image
     *
     * @param integer $position Position
     * @param boolean $direction Direction
     * @deprecated since version 1.5.0.1 use Image360::updatePosition() instead
     */
    public function positionImage($position, $direction)
    {
        Tools::displayAsDeprecated();

        $position = (int)$position;
        $direction = (int)$direction;

        // temporary position
        $high_position = Image360::getHighestPosition($this->id_product) + 1;

        Db::getInstance()->execute('
		UPDATE ' . _DB_PREFIX_ . 'image360
		SET position = ' . (int)$high_position . '
		WHERE id_product = ' . (int)$this->id_product . '
		AND position = ' . ($direction ? $position - 1 : $position + 1));

        Db::getInstance()->execute('
		UPDATE ' . _DB_PREFIX_ . 'image360
		SET position = position' . ($direction ? '-1' : '+1') . '
		WHERE id_image = ' . (int)$this->id);

        Db::getInstance()->execute('
		UPDATE ' . _DB_PREFIX_ . 'image360
		SET position = ' . $this->position . '
		WHERE id_product = ' . (int)$this->id_product . '
		AND position = ' . (int)$high_position);
    }

    /**
     * Change an image position and update relative positions
     *
     * @param int $way position is moved up if 0, moved down if 1
     * @param int $position new position of the moved image
     * @return int success
     */
    public function updatePosition($way, $position)
    {
        if (!isset($this->id) || !$position)
            return false;

        // < and > statements rather than BETWEEN operator
        // since BETWEEN is treated differently according to databases
        $result = (Db::getInstance()->execute('
			UPDATE ' . _DB_PREFIX_ . 'image360
			SET position= position ' . ($way ? '- 1' : '+ 1') . '
			WHERE position
			' . ($way
                    ? '> ' . (int)$this->position . ' AND position <= ' . (int)$position
                    : '< ' . (int)$this->position . ' AND position >= ' . (int)$position) . '
			AND id_product=' . (int)$this->id_product)
            && Db::getInstance()->execute('
			UPDATE ' . _DB_PREFIX_ . 'image360
			SET position = ' . (int)$position . '
			WHERE id_image = ' . (int)$this->id_image));

        return $result;
    }

    /**
     * Returns the path where a product image should be created (without file format)
     *
     * @return string path
     */
    public function getPathForCreation()
    {
        if (!$this->id)
            return false;
        if (Configuration::get('PS_LEGACY_IMAGES')) {
            if (!$this->id_product)
                return false;
            $path = '360-' . $this->id_product . '-' . $this->id;
        } else {
            $path = $this->getImgPath();
            $this->createImgFolder();
        }
        return _PS_360_IMG_DIR_ . $path;
    }

    public function getURL($image_type = 'home_default')
    {
        return $this->getImgPath() . '-' . $image_type . '.' . $this->image_format;
    }
}