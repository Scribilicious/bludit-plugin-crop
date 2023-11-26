<?php

class pluginCrop extends Plugin {

    /**
     * Initialize
     */
    public function init()
    {
        $this->dbFields = [
            'default-width' => 400,
            'default-height' => 400,
            'max-width' => 2000,
            'max-height' => 2000,
            'quality' => 75,
            'caching' => true,
        ];
    }

    /**
     * Check before saving
     */
    public function post()
    {
        // If needed clears the cache
        if (isset($_POST['clear-cache'])) {
            $cache_folder = sys_get_temp_dir() . '/crop';
            if (is_dir($cache_folder)) {
                $files = glob($cache_folder . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
        }

        // Writes in the DB
        $this->db['default-width'] = intval($_POST['default-width']) ?: 800;
        $this->db['default-height'] = intval($_POST['default-height']) ?: 600;
        $this->db['max-width'] = intval($_POST['max-width']) ?: 2000;
        $this->db['max-height'] = intval($_POST['max-height']) ?: 1500;
        $this->db['quality'] = intval($_POST['quality']) ?: 75;

        if ($this->db['quality'] > 100) {
            $this->db['quality'] = 100;
        }

        $this->db['caching'] = intval($_POST['caching']);

        // Save the database
        return $this->save();
    }

    /**
     * Creates the config form
     */
    public function form()
    {
        global $L;

        $html = $this->payMe();

        $html .= '<div class="alert alert-primary" role="alert">';
        $html .= $L->get('Crop URL').': '.DOMAIN_BASE.'crop/{image}<br>';
        $html .= $L->get('Crop URL Help');
        $html .= '</div>';

        $html .= '<h2>'.$L->get('Crop Settings').'</h2>';

        $html .= '<div>';
        $html .= '<label>'.$L->get('Crop Default width').'</label>';
        $html .= '<input name="default-width" type="text" value="'.$this->getValue('default-width').'">';
        $html .= '</div>';

        $html .= '<div>';
        $html .= '<label>'.$L->get('Crop Default height').'</label>';
        $html .= '<input name="default-height" type="text" value="'.$this->getValue('default-height').'">';
        $html .= '</div>';

        $html .= '<div>';
        $html .= '<label>'.$L->get('Crop Max width').'</label>';
        $html .= '<input name="max-width" type="text" value="'.$this->getValue('max-width').'">';
        $html .= '</div>';

        $html .= '<div>';
        $html .= '<label>'.$L->get('Crop Max height').'</label>';
        $html .= '<input name="max-height" type="text" value="'.$this->getValue('max-height').'">';
        $html .= '</div>';

        $html .= '<div>';
        $html .= '<label>'.$L->get('Crop Quality').'</label>';
        $html .= '<input name="quality" type="text" value="'.$this->getValue('quality').'">';
        $html .= '</div>';

        $html .= '<div>';
        $html .= '<label>'.$L->get('Crop Caching').'</label>';
        $html .= '<select name="caching">';
        $html .= '<option value="true" ' . ($this->getValue('caching') === true ? 'selected' : '') . '>' . $L->get('Crop Enabled') . '</option>';
        $html .= '<option value="false" ' . ($this->getValue('caching') === false ? 'selected' : '') . '>' . $L->get('Crop Disabled') . '</option>';
        $html .= '</select>';
        $html .= '</div>';

        $html .= '<div>';
        $html .= '<button name="clear-cache" class="btn btn-primary my-2" type="submit">'.$L->get('Crop Clear cache').'</button>';
        $html .= '</div>';

        $html .= $this->footer();

        return $html;
    }

    /**
     * Does the crop hook
     */
    public function beforeAll()
    {
        $URI = $this->webhook('crop', $returnsAfterURI=true, $fixed=false);
        if ($URI===false) {
            return false;
        }

        $parameters = $this->getEndpointParameters($URI);
        if (empty($parameters)) {
            $this->response(400, 'Bad Request', array('message'=>'Missing endpoint parameters.'));
        }

        $image = null;
        $width = null;
        $height = null;
        $quality = null;
        $sharp = false;

        foreach ($parameters as $parameter) {
            if (!$image) {
                if (mb_strpos($parameter, 'w') === 0) {
                    $width = intval(substr($parameter, 1));
                    continue;
                } elseif (mb_strpos($parameter, 'h') === 0) {
                    $height = intval(substr($parameter, 1));
                    continue;
                } elseif (mb_strpos($parameter, 'q') === 0) {
                    $quality = intval(substr($parameter, 1));
                    continue;
                } elseif ($parameter === 's') {
                    $sharp = true;
                    continue;
                }
            }
            $image[] = $parameter;
        }

        $image = implode('/', $image);
        $image_file = PATH_ROOT . $image;

        if (!file_exists($image_file)) {
            $this->response(400, 'Bad Request', array('message'=>'File "' . $image . '" doesn\'t exists.'));
        }

        $cache_file = $this->getValue('caching') ? sys_get_temp_dir() . '/crop/' . md5(serialize($parameters)) . 'tmp' : false;

        if (!$width || $width > $this->getValue('max-width')) {
            $width = $this->getValue('default-width');
        }

        if (!$height || $height > $this->getValue('max-height')) {
            $height = $this->getValue('default-height');
        }

        if (!$quality || $quality > 100) {
            $quality = $this->getValue('quality');
        }

        // Does the caching part
        $cache_file = $this->getValue('caching') ? (sys_get_temp_dir() . '/crop/' . sha1(implode(':', $parameters))) : null;

        $type = $this->imageType($image_file);

        if ($cache_file && file_exists($cache_file)) {
            $type = $this->imageType($image_file);
            $this->createHeader($type);
            readfile($cache_file);
            die();
        }

        // Check if the image is a jpg, gif, or png
        $image_info = getimagesize($image_file);
        $image_type = $image_info[2];
        if ($image_type !== IMAGETYPE_JPEG && $image_type !== IMAGETYPE_GIF && $image_type !== IMAGETYPE_PNG) {
            $this->response(400, 'Bad Request', array('message'=>'Invalid image format.'));
        }

        $image = $this->cropImage(
            imagecreatefromstring(file_get_contents($image_file)),
            $width,
            $height,
            $sharp
        );

        if ($cache_file) {
            $this->writeImage($image, $type, $cache_file, $quality);
        }

        imagedestroy($image);

        $this->createHeader($type);
        $this->viewImage($image, $type, $quality);
    }

    /**
     * Crop an image
     */
    private function cropImage($image, $width, $height, $sharp = false)
    {
        $max_width = imagesx($image);
        $max_height = imagesy($image);
        $new_width = $width;
        $new_height = $height;

        if ($height === false) {
            $height = round(($width / $max_width) * $max_height);
        }

        $x = 0;
        $y = 0;

        $output = imagecreatetruecolor($width, $height);

        $new_width = $width;
        $new_height = round(($width/$max_width) * $max_height);
        if ($new_height < $height) {
            $new_height = $height;
            $new_width = round(($height/$max_height) * $max_width);
        }

        $x = round(($width/2) - ($new_width/2));
        $y = round(($height/2) - ($new_height/2));

        if ($sharp) {
            imagecopyresized($output, $image, $x, $y, 0, 0, $new_width, $new_height, $max_width, $max_height);
        } else {
            imagecopyresampled($output, $image, $x, $y, 0, 0, $new_width, $new_height, $max_width, $max_height);
        }

        $image = $output;
        imagedestroy($output);

        return $image;
    }

    /**
     * Create Header
     */
    private function createHeader($sType='jpeg') {
        header('Cache-Control: max-age=31536000');
        header('Content-Type: image/' . $sType);
    }

    /**
     * Get Imagetype
     */
    private function imageType($filename) {
        $return = null;

        if ($filename) {
            $return = strtolower(str_replace('jpg', 'jpeg', pathinfo($filename, PATHINFO_EXTENSION)));
            if (!in_array($return, ['jpeg', 'gif', 'png', 'webp'])) {
                $return = null;
            }
        }

        return $return;
    }

    /**
     * View the Image
     */
    private function viewImage($image, $type='jpeg', $quality=100) {
        switch ($type) {
            case 'gif':
                imagegif($image);
                break;

            case 'png':
                // png quality is 0 - 9, recalculate if it is in %
                $quality = $quality > 9 ? round($quality / 10) - 1 : $quality;
                imagepng($image, null, $quality);
                break;

            case 'webp':
                imagewebp($image, null, $quality);
                break;

            case 'jpeg':
            default:
                imagejpeg($image,null, $quality);
                break;
        }
    }

    /**
     * Write the image
     */
    private function writeImage($image, $type, $filename, $quality=100) {
        switch ($type) {
            case 'gif':
                imagegif($image, $filename);
                break;

            case 'png':
                // png quality is 0 - 9, recalculate if it is in %
                $quality = $quality > 9 ? round($quality / 10) - 1 : $quality;
                imagepng($image, $filename, $quality);
                break;

            case 'webp':
                imagewebp($image, $filename, $quality);
                break;

            case 'jpeg':
            default:
                imagejpeg($image, $filename, $quality);
                break;
        }

        return file_exists($filename);
    }

    /**
     * Gets the endpoint parameters
     */
    private function getEndpointParameters($URI)
    {
        $URI = ltrim($URI, '/');
        $parameters = explode('/', $URI);

        // Sanitize parameters
        foreach ($parameters as $key=>$value) {
            $parameters[$key] = Sanitize::html($value);
        }

        return $parameters;
    }

    /**
     * Response
     */
    private function response($code=200, $message='OK', $data=array())
    {
        header('HTTP/1.1 '.$code.' '.$message);
        header('Access-Control-Allow-Origin: *');
        header('Content-Type: application/json');
        $json = json_encode($data);
        die($json);
    }

    /**
     * Creates the Support Me Button...
     */
    private function payMe() {
        global $L;

        $icons = ['💸', '🥹', '☕️', '🍻', '👾', '🍕'];
        shuffle($icons);
        $html = '<div class="bg-light text-center border mt-3 p-3">';
        $html .= '<p class="mb-2">' . $L->get('Please support Mr.Bot') . '</p>';
        $html .= '<a style="background: #ffd11b;box-shadow: 2px 2px 5px #ccc;padding: 0 10px;border-radius: 50%;width: 60px;display: block;text-align: center;margin: auto;height: 60px; font-size: 40px; line-height: 60px;" href="https://www.buymeacoffee.com/iambot" target="_blank" title="Buy me a coffee...">' . $icons[0] . '</a>';
        $html .= '</div><br>';

        return $html;
    }

    /**
     * Creates the Footer
     */
    private function footer() {
        $html = '<div class="text-center mt-3 p-3" style="opacity: 0.6;">';
        $html .= '<p class="mb-2">© ' . date('Y') . ' by <a href="https://github.com/Scribilicious" target="_blank" title="Visit GitHub page...">Mr.Bot</a>, Licensed under <a href="https://raw.githubusercontent.com/Scribilicious/MIT/main/LICENSE" target="_blank" title="view license...">MIT</a>.</p>';
        $html .= '</div><br>';

        return $html;
    }
}
