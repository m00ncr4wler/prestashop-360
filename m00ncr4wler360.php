<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

include_once dirname(__FILE__) . '/classes/Image360.php';

/**
 * Class Product360
 */
class m00ncr4wler360 extends Module
{
    /**
     * @var int|null
     */
    protected $max_file_size = null;
    /**
     * @var int|null
     */
    protected $max_image_size = null;
    /**
     * @var int
     */
    protected $max_execution_time = 7200;
    /**
     * @var bool
     */
    protected $errors = false;

    /**
     *
     */
    public function __construct()
    {
        $this->name = 'm00ncr4wler360';
        $this->tab = 'front_office_features';
        $this->version = '0.1.1';
        $this->author = 'm00ncr4wler - David Heinz';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Easy 360°');
        $this->description = $this->l('Easy 360° Viewer.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        $this->max_file_size = (int)(Configuration::get('PS_LIMIT_UPLOAD_FILE_VALUE') * 1000000);
        $this->max_image_size = (int)Configuration::get('PS_PRODUCT_PICTURE_MAX_SIZE');
        ini_set('max_execution_time', $this->max_execution_time); // ini_set may be disabled, we need the real value
        $this->max_execution_time = (int)ini_get('max_execution_time');
    }

    /**
     * @return bool
     * @throws PrestaShopException
     */
    public function install()
    {
        if (Shop::isFeatureActive())
            Shop::setContext(Shop::CONTEXT_ALL);

        if (!parent::install()
            || !$this->prepareFolder('add')
            || !$this->prepareTable('add')
            || !$this->registerHook('displayAdminProductsExtra')
            || !$this->registerHook('displayFooterProduct')
            || !$this->registerHook('header')
            || !Configuration::updateValue('PRODUCT360_IMAGE_TYPE', 5)
            || !Configuration::updateValue('PRODUCT360_IMAGE_TYPE_FANCYBOX', 6)
            || !$this->installConf()
        ) {
            return false;
        }
        return true;
    }

    /**
     * @param $method
     * @return bool
     */
    public function prepareFolder($method)
    {
        switch ($method) {
            case 'add':
                if (!file_exists(_PS_360_IMG_DIR_)) {
                    // Apparently sometimes mkdir cannot set the rights, and sometimes chmod can't. Trying both.
                    $success = @mkdir(_PS_360_IMG_DIR_, 0775, true);
                    $chmod = @chmod(_PS_360_IMG_DIR_, 0775);

                    // Create an index.php file in the new folder
                    if (($success || $chmod)
                        && !file_exists(_PS_360_IMG_DIR_ . 'index.php')
                        && file_exists(_PS_PROD_IMG_DIR_ . 'index.php')
                    )
                        @copy(_PS_PROD_IMG_DIR_ . 'index.php', _PS_360_IMG_DIR_ . 'index.php');
                }
                break;

            case 'remove':
                /** TODO: DONT REMOVE THE FOLDER - LET THE USER DECIDE
                 * if (file_exists(_PS_360_IMG_DIR_)) {
                 * @rmdir(_PS_360_IMG_DIR_);
                 * }
                 */
                break;
        }
        return true;
    }

    /**
     * @param $method
     * @return bool
     */
    public function prepareTable($method)
    {
        switch ($method) {
            case 'add':
                $sql = "CREATE TABLE " . _DB_PREFIX_ . "image360 (
                    id_image int(10) unsigned NOT NULL AUTO_INCREMENT,
                    id_product int(10) unsigned NOT NULL,
                    position smallint(2) unsigned NOT NULL DEFAULT '0',
                    cover tinyint(1) unsigned NOT NULL DEFAULT '0',
                    PRIMARY KEY (id_image),
                    UNIQUE KEY idx_product_image (id_image,id_product,cover),
                    KEY image_product (id_product),
                    KEY id_product_cover (id_product,cover)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
                break;

            case 'remove':
                $sql = "DROP TABLE IF EXISTS " . _DB_PREFIX_ . "image360;";
                break;
        }

        if (!Db::getInstance()->Execute($sql)) {
            return false;
        }
        return true;
    }

    /**
     * @return bool
     */
    protected function installConf()
    {
        $ok = true;

        foreach ($this->getConf('bool') as $id => $conf) {
            if (!Configuration::updateValue('PRODUCT360_BOOL_' . strtoupper($id), $conf['default'])) {
                $ok = false;
            }
        }

        return $ok;
    }

    /**
     * @param $type
     * @return mixed
     */
    protected function getConf($type)
    {
        $confArray = $this->getConfArray();
        return $confArray[$type];
    }

    /**
     * @return array
     */
    protected function getConfArray()
    {
        return array(
            'bool' => array(
                'clickfree' => array(
                    'label' => 'Clickfree',
                    'desc' => 'Binds to mouse leave/enter events instead of down/up mouse events.',
                    'default' => false,
                ),
                'cw' => array(
                    'label' => 'Clockwise',
                    'desc' => 'If your Reel image motion doesn\'t follow the mouse when dragged (moves in opposite direction), set this to true to indicate clockwise organization of frames.',
                    'default' => false,
                ),
                'shy' => array(
                    'label' => 'Shy',
                    'desc' => 'In shy mode, Reel will preinitialize, but won\'t load until actually clicked.',
                    'default' => false,
                ),
                'responsive' => array(
                    'label' => 'Responsive',
                    'desc' => 'If switched to responsive mode, Reel will obey dimensions of its parent container, will grow to fit and will adjust the interaction and UI accordingly (and also on resize).',
                    'default' => false,
                ),
                'throwable' => array(
                    'label' => 'Throwable',
                    'desc' => 'Allows drag & throw interaction.',
                    'default' => false,
                ),
                'steppable' => array(
                    'label' => 'Steppable',
                    'desc' => 'Allows to step the view by clicking on image.',
                    'default' => false,
                ),
                'draggable' => array(
                    'label' => 'Draggable',
                    'desc' => 'Allows mouse or finger drag interaction when true.',
                    'default' => true,
                ),
                'loops' => array(
                    'label' => 'Loops',
                    'desc' => 'Can be used to suppress default wrap around behavior of the sequence. Use this option when your captured sequence is a incomplete revolution.',
                    'default' => true,
                ),
                'orientable' => array(
                    'label' => 'Mobile: Orientable',
                    'desc' => 'Enables interaction via device\'s built-in gyroscope.',
                    'default' => false,
                ),
            ),
        );
    }

    /**
     * @return bool
     */
    public function uninstall()
    {
        if (!parent::uninstall()
            || !$this->prepareFolder('remove')
            || !$this->prepareTable('remove')
            || !$this->unregisterHook('displayAdminProductsExtra')
            || !$this->unregisterHook('displayFooterProduct')
            || !$this->registerHook('header')
            || !Configuration::deleteByName('PRODUCT360_IMAGE_TYPE')
            || !Configuration::deleteByName('PRODUCT360_IMAGE_TYPE_FANCYBOX')
            || !$this->uninstallConf()
        ) {
            return false;
        }
        return true;
    }

    /**
     * @return bool
     */
    protected function uninstallConf()
    {
        $ok = true;

        foreach ($this->getConf('bool') as $id => $conf) {
            if (!Configuration::deleteByName('PRODUCT360_BOOL_' . strtoupper($id))) {
                $ok = false;
            }
        }

        return $ok;
    }

    /**
     * @param array $params
     * @return string
     */
    public function hookDisplayAdminProductsExtra($params)
    {
        $id_product = (int)Tools::getValue('id_product');
        $id_category = (int)Tools::getValue('id_category');
        $token = Tools::getAdminTokenLite('AdminProducts');
        $image_type = $this->getImageTypeById((int)ConfigurationCore::get('PRODUCT360_IMAGE_TYPE'));
        if (Validate::isLoadedObject($product = new Product($id_product))) {
            $count_images = Image360::getImagesTotal($id_product);
            $images = Image360::getImages($id_product);

            foreach ($images as $k => $image)
                $images[$k] = new Image360($image['id_image']);

            $languages = Language::getLanguages(true);
            $image_uploader = new HelperImageUploader('file360');
            $image_uploader->setTemplateDirectory(__DIR__ . '/views/templates/admin/helpers/uploader');
            $image_uploader->setMultiple(!(Tools::getUserBrowser() == 'Apple Safari' && Tools::getUserPlatform() == 'Windows'))->setUseAjax(true)->setUrl(Context::getContext()->link->getAdminLink('AdminProducts') . '&ajax=1&id_product=' . (int)$id_product . '&action=addProductImage360');
            $this->context->smarty->assign(array(
                'id_product' => $id_product,
                'id_category_default' => $id_category,
                'countImages' => $count_images,
                'images' => $images,
                'iso_lang' => $languages[0]['iso_code'],
                'token' => $token,
                'table' => 'product',
                'max_image_size' => $this->max_image_size / 1024 / 1024,
                'up_filename' => (string)Tools::getValue('virtual_product_filename_attribute'),
                'default_language' => (int)Configuration::get('PS_LANG_DEFAULT'),
                'image_uploader' => $image_uploader->render(),
                'imageType' => $image_type['name'],
            ));

            return $this->display(__FILE__, 'views/templates/admin/' . $this->name . '.tpl');
        }
    }

    /**
     * @param int $id
     * @return array
     */
    public function getImageTypeById($id)
    {
        $imagesTypes = ImageType::getImagesTypes('products');
        foreach ($imagesTypes as $imageType) {
            if ($imageType['id_image_type'] == $id) {
                return $imageType;
            }
        }
        return $imagesTypes[1];
    }

    /**
     * @param array $params
     * @return string
     */
    public function hookDisplayFooterProduct($params)
    {
        $image_start = 1;
        $images = array();
        $image_type = $this->getImageTypeById((int)ConfigurationCore::get('PRODUCT360_IMAGE_TYPE'));
        $image_type_fancybox = $this->getImageTypeById((int)ConfigurationCore::get('PRODUCT360_IMAGE_TYPE_FANCYBOX'));
        $imgs = Image360::getImages((int)Tools::getValue('id_product'));
        foreach ($imgs as $image) {
            $img = new Image360((int)$image['id_image']);
            $images[] = $img->getURL($image_type['name']);
            if ($img->cover) {
                $image_start = (int)$img->position;
            }
        }
        if(!empty($images)) {
            $smarty = array(
                'id_image_start' => $image_start,
                'image_type' => $image_type['name'],
                'image_type_fancybox' => $image_type_fancybox['name'],
                'image_start' => $images[$image_start - 1],
                'image_width' => $image_type['width'],
                'image_height' => $image_type['height'],
                'image_count' => count($images),
                'images' => $images,
            );
            $this->assignConf($smarty);
            $this->context->smarty->assign($smarty);
            return $this->display(__FILE__, 'views/templates/front/' . $this->name . '.tpl');
        }
    }

    /**
     * @param $assign
     */
    protected function assignConf(&$assign)
    {
        foreach ($this->getConf('bool') as $id => $conf) {
            $assign['reel_' . $id] = (Configuration::get('PRODUCT360_BOOL_' . strtoupper($id))) ? 'true' : 'false';
        }
    }

    /**
     * @param array $params
     */
    public function hookDisplayHeader($params)
    {
        $allowedControllers = array('product');
        $c = $this->context->controller;
        if (isset($c->php_self) && in_array($c->php_self, $allowedControllers)) {
            $this->context->controller->addCSS($this->_path . 'views/templates/css/' . $this->name . '.css', 'all');
            $this->context->controller->addJS($this->_path . 'views/templates/js/jquery.reel.js');
        }
    }

    /**
     * @return string
     */
    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('setting' . $this->name)) {
            $imageType = (int)Tools::getValue('PRODUCT360_IMAGE_TYPE');
            $imageTypeFancybox = (int)Tools::getValue('PRODUCT360_IMAGE_TYPE_FANCYBOX');

            if (!$imageType || !Validate::isUnsignedId($imageType)
                && !$imageTypeFancybox || !Validate::isUnsignedId($imageTypeFancybox)
                && !$this->isValidatedConf()
            )
                $output .= $this->displayError($this->l('Invalid Configuration value'));
            else {
                Configuration::updateValue('PRODUCT360_IMAGE_TYPE', $imageType);
                Configuration::updateValue('PRODUCT360_IMAGE_TYPE_FANCYBOX', $imageTypeFancybox);
                $this->updateConf();
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }

        if (Tools::isSubmit('regeneration' . $this->name)) {
            if (!$this->_regenerateThumbnails('all', Tools::getValue('erase')))
                if (is_array($this->errors))
                    foreach ($this->errors as $error)
                        $output .= $this->displayError($error);
                else
                    $output .= $this->displayConfirmation($this->l('Thumbnails successfully regenerated'));
        }

        return $output . $this->displayForm();
    }

    /**
     * @return bool
     */
    protected function isValidatedConf()
    {
        $valid = true;
        foreach ($this->getConf('bool') as $id => $conf) {
            $tmp = (bool)Tools::getValue('PRODUCT360_BOOL_' . strtoupper($id));
            if (!$tmp || !Validate::isBool($tmp))
                $valid = false;
        }

        return $valid;
    }

    /**
     *
     */
    protected function updateConf()
    {
        foreach ($this->getConf('bool') as $id => $conf) {
            Configuration::updateValue('PRODUCT360_BOOL_' . strtoupper($id), (bool)Tools::getValue('PRODUCT360_BOOL_' . strtoupper($id)));
        }
    }

    /**
     * @param string $type
     * @param bool $deleteOldImages
     * @return bool
     */
    protected function _regenerateThumbnails($type = 'all', $deleteOldImages = false)
    {
        $this->start_time = time();
        $languages = Language::getLanguages(false);

        $process = array(
            array('type' => 'products', 'dir' => _PS_360_IMG_DIR_),
        );

        // Launching generation process
        foreach ($process as $proc) {
            if ($type != 'all' && $type != $proc['type'])
                continue;

            // Getting format generation
            $formats = ImageType::getImagesTypes($proc['type']);
            if ($type != 'all') {
                $format = strval(Tools::getValue('format_' . $type));
                if ($format != 'all')
                    foreach ($formats as $k => $form)
                        if ($form['id_image_type'] != $format)
                            unset($formats[$k]);
            }
            if ($deleteOldImages)
                $this->_deleteOldImages($proc['dir'], $formats, ($proc['type'] == 'products' ? true : false));
            if (($return = $this->_regenerateNewImages($proc['dir'], $formats, ($proc['type'] == 'products' ? true : false))) === true) {
                if (!count($this->errors))
                    $this->errors[] = sprintf(Tools::displayError('Cannot write images for this type: %s. Please check the %s folder\'s writing permissions.'), $proc['type'], $proc['dir']);
            } elseif ($return == 'timeout')
                $this->errors[] = Tools::displayError('Only part of the images have been regenerated. The server timed out before finishing.');
            else {
                if ($proc['type'] == 'products')
                    if ($this->_regenerateWatermark($proc['dir']) == 'timeout')
                        $this->errors[] = Tools::displayError('Server timed out. The watermark may not have been applied to all images.');
                if (!count($this->errors))
                    if ($this->_regenerateNoPictureImages($proc['dir'], $formats, $languages))
                        $this->errors[] = sprintf(
                            Tools::displayError('Cannot write "No picture" image to (%s) images folder. Please check the folder\'s writing permissions.'),
                            $proc['type']
                        );
            }
        }
        return (count($this->errors) > 0 ? false : true);
    }

    /**
     * @param string $dir
     * @param array $type
     * @param bool $product
     */
    protected function _deleteOldImages($dir, $type, $product = false)
    {
        if (!is_dir($dir))
            return false;
        $toDel = scandir($dir);

        foreach ($toDel as $d)
            foreach ($type as $imageType)
                if (preg_match('/^[0-9]+\-' . ($product ? '[0-9]+\-' : '') . $imageType['name'] . '\.jpg$/', $d)
                    || (count($type) > 1 && preg_match('/^[0-9]+\-[_a-zA-Z0-9-]*\.jpg$/', $d))
                    || preg_match('/^([[:lower:]]{2})\-default\-' . $imageType['name'] . '\.jpg$/', $d)
                )
                    if (file_exists($dir . $d))
                        unlink($dir . $d);

        // delete product images using new filesystem.
        if ($product) {
            $productsImages = Image360::getAllImages();
            foreach ($productsImages as $image) {
                $imageObj = new Image360($image['id_image']);
                $imageObj->id_product = $image['id_product'];
                if (file_exists($dir . $imageObj->getImgFolder())) {
                    $toDel = scandir($dir . $imageObj->getImgFolder());
                    foreach ($toDel as $d)
                        foreach ($type as $imageType)
                            if (preg_match('/^[0-9]+\-' . $imageType['name'] . '\.jpg$/', $d) || (count($type) > 1 && preg_match('/^[0-9]+\-[_a-zA-Z0-9-]*\.jpg$/', $d)))
                                if (file_exists($dir . $imageObj->getImgFolder() . $d))
                                    unlink($dir . $imageObj->getImgFolder() . $d);
                }
            }
        }
    }

    /**
     * @param string $dir
     * @param array $type
     * @param bool $productsImages
     * @return bool|string
     */
    protected function _regenerateNewImages($dir, $type, $productsImages = false)
    {
        if (!is_dir($dir))
            return false;

        $errors = false;
        if ($productsImages) {
            foreach (Image360::getAllImages() as $image) {
                $imageObj = new Image360($image['id_image']);
                $existing_img = $dir . $imageObj->getExistingImgPath() . '.jpg';
                if (file_exists($existing_img) && filesize($existing_img)) {
                    foreach ($type as $imageType)
                        if (!file_exists($dir . $imageObj->getExistingImgPath() . '-' . stripslashes($imageType['name']) . '.jpg'))
                            if (!ImageManager::resize($existing_img, $dir . $imageObj->getExistingImgPath() . '-' . stripslashes($imageType['name']) . '.jpg', (int)($imageType['width']), (int)($imageType['height']))) {
                                $errors = true;
                                $this->errors[] = Tools::displayError(sprintf('Original image is corrupt (%s) for product ID %2$d or bad permission on folder', $existing_img, (int)$imageObj->id_product));
                            }
                } else {
                    $errors = true;
                    $this->errors[] = Tools::displayError(sprintf('Original image is missing or empty (%1$s) for product ID %2$d', $existing_img, (int)$imageObj->id_product));
                }
                if (time() - $this->start_time > $this->max_execution_time - 4) // stop 4 seconds before the timeout, just enough time to process the end of the page on a slow server
                    return 'timeout';
            }

        }
        return $errors;
    }

    /**
     * @param string $dir
     * @return string
     * @throws PrestaShopDatabaseException
     */
    protected function _regenerateWatermark($dir)
    {
        $result = Db::getInstance()->executeS('
		SELECT m.`name` FROM `' . _DB_PREFIX_ . 'module` m
		LEFT JOIN `' . _DB_PREFIX_ . 'hook_module` hm ON hm.`id_module` = m.`id_module`
		LEFT JOIN `' . _DB_PREFIX_ . 'hook` h ON hm.`id_hook` = h.`id_hook`
		WHERE h.`name` = \'actionWatermark\' AND m.`active` = 1');

        if ($result && count($result)) {
            $productsImages = Image360::getAllImages();
            foreach ($productsImages as $image) {
                $imageObj = new Image360($image['id_image']);
                if (file_exists($dir . $imageObj->getExistingImgPath() . '.jpg'))
                    foreach ($result as $module) {
                        $moduleInstance = Module::getInstanceByName($module['name']);
                        if ($moduleInstance && is_callable(array($moduleInstance, 'hookActionWatermark')))
                            call_user_func(array($moduleInstance, 'hookActionWatermark'), array('id_image' => $imageObj->id, 'id_product' => $imageObj->id_product));

                        if (time() - $this->start_time > $this->max_execution_time - 4) // stop 4 seconds before the timeout, just enough time to process the end of the page on a slow server
                            return 'timeout';
                    }
            }
        }
    }

    /**
     * @param string $dir
     * @param array $type
     * @param $languages
     * @return bool
     */
    protected function _regenerateNoPictureImages($dir, $type, $languages)
    {
        $errors = false;
        foreach ($type as $image_type)
            foreach ($languages as $language) {
                $file = $dir . $language['iso_code'] . '.jpg';
                if (!file_exists($file))
                    $file = _PS_PROD_IMG_DIR_ . Language::getIsoById((int)Configuration::get('PS_LANG_DEFAULT')) . '.jpg';
                if (!file_exists($dir . $language['iso_code'] . '-default-' . stripslashes($image_type['name']) . '.jpg'))
                    if (!ImageManager::resize($file, $dir . $language['iso_code'] . '-default-' . stripslashes($image_type['name']) . '.jpg', (int)$image_type['width'], (int)$image_type['height']))
                        $errors = true;
            }
        return $errors;
    }

    /**
     * @return string
     */
    public function displayForm()
    {
        // Get default Language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Init Fields form array
        $setting_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Easy 360° Settings'),
                'icon' => 'icon-cogs'
            ),
            'input' => array(
                array(
                    'name' => 'PRODUCT360_IMAGE_TYPE',
                    'label' => $this->l('Image'),
                    'desc' => $this->l('Set the image size of 360° viewer that you would like to display on product page (default: large).'),
                    'type' => 'select',
                    'options' => array(
                        'query' => $this->getImagesTypesOptions('products'),
                        'id' => 'value',
                        'name' => 'name'
                    ),
                ),
                array(
                    'name' => 'PRODUCT360_IMAGE_TYPE_FANCYBOX',
                    'label' => $this->l('Thickbox'),
                    'desc' => $this->l('Set the thickbox image size of 360° viewer that you would like to display on thickbox view (default: large).'),
                    'type' => 'select',
                    'options' => array(
                        'query' => $this->getImagesTypesOptions('products'),
                        'id' => 'value',
                        'name' => 'name'
                    ),
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save')
            )
        );
        $this->getConfFromInput($setting_form[0]['form']['input']);

        $regeneration_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Regenerate thumbnails'),
                'icon' => 'icon-picture'
            ),
            'description' => $this->l('Regenerates thumbnails for all existing images') . '<br/>' .
                $this->l('Please be patient. This can take several minutes.') . '<br/>' .
                $this->l('Be careful! Manually uploaded thumbnails will be erased and replaced by automatically generated thumbnails.'),
            'input' => array(
                array(
                    'name' => 'erase',
                    'label' => $this->l('Erase previous images'),
                    'desc' => $this->l('Select "No" only if your server timed out and you need to resume the regeneration.'),
                    'type' => 'switch',
                    'values' => array(
                        array(
                            'id' => 'erase_on',
                            'value' => 1,
                            'label' => $this->l('Yes')
                        ),
                        array(
                            'id' => 'erase_off',
                            'value' => 0,
                            'label' => $this->l('No')
                        )
                    ),
                ),
            ),
            'submit' => array(
                'title' => $this->l('Regenerate thumbnails'),
                'icon' => 'process-icon-cogs'
            )
        );

        $setting = new HelperForm();
        $setting->module = $this;
        $setting->name_controller = $this->name;
        $setting->token = Tools::getAdminTokenLite('AdminModules');
        $setting->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $setting->default_form_language = $default_lang;
        $setting->allow_employee_form_lang = $default_lang;
        $setting->title = $this->displayName;
        $setting->submit_action = 'setting' . $this->name;

        $setting->fields_value['PRODUCT360_IMAGE_TYPE'] = Configuration::get('PRODUCT360_IMAGE_TYPE');
        $setting->fields_value['PRODUCT360_IMAGE_TYPE_FANCYBOX'] = Configuration::get('PRODUCT360_IMAGE_TYPE_FANCYBOX');
        $this->getConfFieldVars($setting);

        $regeneration = new HelperForm();
        $regeneration->module = $this;
        $regeneration->name_controller = $this->name;
        $regeneration->token = Tools::getAdminTokenLite('AdminModules');
        $regeneration->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $regeneration->default_form_language = $default_lang;
        $regeneration->allow_employee_form_lang = $default_lang;
        $regeneration->title = $this->displayName;
        $regeneration->submit_action = 'regeneration' . $this->name;

        $regeneration->fields_value['erase'] = 0;

        return $setting->generateForm($setting_form) . $regeneration->generateForm($regeneration_form);
    }

    /**
     * @return array
     */
    public function getImagesTypesOptions()
    {
        $options = array();
        $imagesTypes = ImageType::getImagesTypes('products');
        foreach ($imagesTypes as $imageType) {
            $options[] = array(
                'value' => $imageType['id_image_type'],
                'name' => $this->l($imageType['name']) . ' ' . $imageType['width'] . ' x ' . $imageType['height'],
            );
        }

        return $options;
    }

    /**
     * @param $form_input
     */
    protected function getConfFromInput(&$form_input)
    {
        foreach ($this->getConf('bool') as $id => $conf) {
            array_push($form_input, array(
                'name' => 'PRODUCT360_BOOL_' . strtoupper($id),
                'label' => $this->l($conf['label']),
                'desc' => $this->l($conf['desc']),
                'type' => 'switch',
                'values' => array(
                    array(
                        'id' => $id . '_on',
                        'value' => 1,
                        'label' => $this->l('Yes')
                    ),
                    array(
                        'id' => $id . '_off',
                        'value' => 0,
                        'label' => $this->l('No')
                    )
                ),
            ));
        }
    }

    /**
     * @param $helperForm
     */
    protected function getConfFieldVars(&$helperForm)
    {
        foreach ($this->getConf('bool') as $id => $conf) {
            $helperForm->fields_value['PRODUCT360_BOOL_' . strtoupper($id)] = Configuration::get('PRODUCT360_BOOL_' . strtoupper($id));
        }
    }
}
