<?php

class AdminProductsController extends AdminProductsControllerCore
{
    public function __construct()
    {
        include_once(_PS_MODULE_DIR_ . 'm00ncr4wler360/classes/Image360.php');
        parent::__construct();
    }

    public function ajaxProcessAddProductImage360()
    {
        $id_product = (int)Tools::getValue('id_product');

        if (!Validate::isLoadedObject(new Product($id_product))) {
            $files = array();
            $files[0]['error'] = Tools::displayError('Cannot add image because product creation failed.');
        }

        $image_uploader = new HelperImageUploader('file360');
        $image_uploader->setAcceptTypes(array('jpeg', 'gif', 'png', 'jpg'))->setMaxSize($this->max_image_size);
        $files = $image_uploader->process();

        foreach ($files as &$file) {
            $image = new Image360();
            $image->id_product = $id_product;
            $image->position = Image360::getHighestPosition($id_product) + 1;

            if (!Image360::getCover($image->id_product))
                $image->cover = 1;
            else
                $image->cover = 0;

            if (isset($file['error']) && (!is_numeric($file['error']) || $file['error'] != 0))
                continue;

            if (!$image->add())
                $file['error'] = Tools::displayError('Error while creating additional image');
            else {
                if (!$new_path = $image->getPathForCreation()) {
                    $file['error'] = Tools::displayError('An error occurred during new folder creation');
                    continue;
                }

                $error = 0;

                if (!ImageManager::resize($file['save_path'], $new_path . '.' . $image->image_format, null, null, 'jpg', false, $error)) {
                    switch ($error) {
                        case ImageManager::ERROR_FILE_NOT_EXIST :
                            $file['error'] = Tools::displayError('An error occurred while copying image, the file does not exist anymore.');
                            break;

                        case ImageManager::ERROR_FILE_WIDTH :
                            $file['error'] = Tools::displayError('An error occurred while copying image, the file width is 0px.');
                            break;

                        case ImageManager::ERROR_MEMORY_LIMIT :
                            $file['error'] = Tools::displayError('An error occurred while copying image, check your memory limit.');
                            break;

                        default:
                            $file['error'] = Tools::displayError('An error occurred while copying image.');
                            break;
                    }
                    continue;
                } else {
                    $imagesTypes = ImageType::getImagesTypes('products');
                    foreach ($imagesTypes as $imageType) {
                        if (!ImageManager::resize($file['save_path'], $new_path . '-' . stripslashes($imageType['name']) . '.' . $image->image_format, $imageType['width'], $imageType['height'], $image->image_format)) {
                            $file['error'] = Tools::displayError('An error occurred while copying image:') . ' ' . stripslashes($imageType['name']);
                            continue;
                        }
                    }
                }

                unlink($file['save_path']);
                //Necesary to prevent hacking
                unset($file['save_path']);
                Hook::exec('actionWatermark', array('id_image' => $image->id, 'id_product' => $id_product));

                if (!$image->update()) {
                    $file['error'] = Tools::displayError('Error while updating status');
                    continue;
                }

                $file['status'] = 'ok';
                $file['id'] = $image->id;
                $file['position'] = $image->position;
                $file['cover'] = $image->cover;
                $file['path'] = $image->getExistingImgPath();
            }
        }

        die(Tools::jsonEncode(array($image_uploader->getName() => $files)));
    }

    public function ajaxProcessDeleteProductImage360()
    {
        $this->display = 'content';
        $res = true;
        /* Delete product image */
        $image = new Image360((int)Tools::getValue('id_image'));
        $this->content['id'] = $image->id;

        $res &= $image->delete();
        // if deleted image was the cover, change it to the first one
        if (!Image360::getCover($image->id_product)) {
            $res &= Db::getInstance()->execute('
			UPDATE ' . _DB_PREFIX_ . 'image360
			SET cover = 1
			WHERE id_product =' . (int)$image->id_product . ' LIMIT 1
			');
        }

        if ($res)
            $this->jsonConfirmation($this->_conf[7]);
        else
            $this->jsonError(Tools::displayError('An error occurred while attempting to delete the product image.'));

        //workaround for not updating $page in AdminController
        $this->context->smarty->assign('content', 1);
        die($this->displayAjax());
    }

    public function displayAjax()
    {
        if ($this->json) {
            $this->context->smarty->assign(array(
                'json' => true,
                'status' => $this->status,
                'content' => true,
            ));
        }
        $this->layout = 'layout-ajax.tpl';
        $this->display_header = false;
        $this->display_footer = false;
        return $this->display();
    }

    public function ajaxProcessUpdateCover360()
    {
        Image360::deleteCover((int)Tools::getValue('id_product'));
        $img = new Image360((int)Tools::getValue('id_image'));
        $img->cover = 1;

        if ($img->update())
            $this->jsonConfirmation($this->_conf[26]);
        else
            $this->jsonError(Tools::displayError('An error occurred while attempting to move this picture.'));
    }

    public function ajaxProcessUpdateImagePosition360()
    {
        $res = false;
        if ($json = Tools::getValue('json')) {
            $res = true;
            $json = stripslashes($json);
            $images = Tools::jsonDecode($json, true);
            foreach ($images as $id => $position) {
                $img = new Image360((int)$id);
                $img->position = (int)$position;
                $res &= $img->update();
            }
        }
        if ($res)
            $this->jsonConfirmation($this->_conf[25]);
        else
            $this->jsonError(Tools::displayError('An error occurred while attempting to move this picture.'));

        //workaround for not updating $page in AdminController
        $this->context->smarty->assign('content', 1);
        die($this->displayAjax());
    }
}