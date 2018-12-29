<?php
/**
 * Created by PhpStorm.
 * User: wuyr@sinoyd.com
 * Date: 2018/12/27
 * Time: 8:28
 */

class Image
{
    const ALIGN_CENTER = 'center';
    const ALIGN_LEFT = 'left';
    const DRIVER_GD = 'gd';
    const DRIVER_IMAGICK = 'imagick';

    private $driver = null;

    public function __construct($driver = self::DRIVER_GD)
    {
        $this->driver = $driver;
    }
    /**
     * 实现多张图片拼装成一张
     * @param array $srcPathArr 原图路径数组
     * @param string $targetPath 目标图片路径
     * @param int $spacing 间距，默认20px
     * @param string $align 图片排列，默认居中
     * @return bool
     */
    public function mergeImages($srcPathArr, $targetPath, $spacing = 20, $align = self::ALIGN_CENTER)
    {
        if (empty($srcPathArr) || empty($targetPath)) {
            return false;
        }
        $images = $this->getImagesSize($srcPathArr);
        if ($this->driver == self::DRIVER_IMAGICK) {
            return $this->imagickMergeImages($images, $targetPath, $spacing, $align);
        } else {
            return $this->gdMergeImages($images, $targetPath, $spacing, $align);
        }
    }

    /**
     * gd库函数实现多张图片拼装成一张
     * @param array $srcPathArr 原图路径数组
     * @param string $targetPath 目标图片路径
     * @param int $spacing 间距
     * @param string $align 图片排列
     * @return bool
     */
    protected function gdMergeImages($srcPathArr, $targetPath, $spacing, $align)
    {
        $width = $srcPathArr['width'];
        $height = $srcPathArr['height'];
        $height += (count($srcPathArr) - 1) * $spacing; //总高度
        $img = ImageCreateTrueColor($width, $height); //创建一个画布，作为拼接后的图片
        $pathInfo = pathinfo($targetPath);
        $targetExt = strtolower($pathInfo['extension']);
        if ($targetExt == 'png') {
            $bg = imagecolorallocatealpha($img, 0, 0, 0, 127);//全透明背景色
            imagealphablending($img, false);//关闭混合模式，以便透明颜色能覆盖原画板
        } else {
            $bg = imagecolorallocate($img, 255, 255, 255);//白色背景
        }
        imagefill($img, 0, 0, $bg);//填充
        $y = 0;
        foreach ($srcPathArr['info'] as $image) {
            $pathInfo = pathinfo($image['path']);
            $ext = strtolower($pathInfo['extension']);
            $imagecreatefunc = 'imagecreatefrom';
            if ($ext == 'jpg') {
                $imagecreatefunc .= 'jpeg';
            } else if (in_array($ext, ['jpeg', 'png', 'gif'])) {
                $imagecreatefunc .= $ext;
            } else {
                $imagecreatefunc .= 'string';
                $image['path'] = file_get_contents($image['path']);
            }

            $imgSrc = $imagecreatefunc($image['path']); //获取原图
            $x = 0;
            if ($align == self::ALIGN_CENTER) {//计算目标图片上的x坐标
                $x = intval(($width - $image['width']) / 2);
            }
            imagecopyresampled($img, $imgSrc, $x, $y, 0, 0, $image['width'], $image['height'], $image['width'], $image['height']);//copy图片到画布上
            if ($ext == 'png') {
                imagealphablending($img, false);
                imagesavealpha($img, true);//保留原图透明度
            }
            imagedestroy($imgSrc);//销毁掉图片
            $y += $image['height'] + $spacing;//计算目标图片上的y坐标
        }
        $imagesavefunc = 'image';
        if (in_array($targetExt, ['jpeg', 'png', 'gif'])) {
            $imagesavefunc .= $targetExt;
        } else {
            $imagesavefunc .= 'jpeg';
        }
        $imagesavefunc($img, $targetPath);
        imagedestroy($img);
        return true;
    }

    /**
     * imagick扩展实现多张图片拼装成一张
     * @param array $srcPathArr 原图路径数组
     * @param string $targetPath 目标图片路径
     * @param int $spacing 间距
     * @param string $align 图片排列
     * @return bool
     */
    protected function imagickMergeImages($srcPathArr, $targetPath, $spacing, $align)
    {
        $width = $srcPathArr['width'];
        $height = $srcPathArr['height'];
        $height += (count($srcPathArr) - 1) * $spacing; //总高度
        $pathInfo = pathinfo($targetPath);
        $targetExt = strtolower($pathInfo['extension']);
        $img = new \Imagick();//创建一个画布，作为拼接后的图片
        $img->newImage($width, $height, 'white');
        $img->setImageFormat($targetExt);
        if ($targetExt == 'png') {
            $img->transparentPaintImage(new \ImagickPixel('white'), 0, '10', 0);
        }
        $y = 0;
        foreach ($srcPathArr['info'] as $image) {
            $imgSrc = new \Imagick($image['path']); //获取原图
            $x = 0;
            if ($align == self::ALIGN_CENTER) {//计算目标图片上的x坐标
                $x = intval(($width - $image['width']) / 2);
            }
            $img->compositeImage($imgSrc, $imgSrc->getImageCompose(), $x, $y);//拼接
            $imgSrc->destroy();//销毁掉图片
            $y += $image['height'] + $spacing;//计算目标图片上的y坐标
        }

        $img->writeimage($targetPath);
        //销毁对象
        $img->destroy();
        return true;
    }

    /**
     * 遍历图片，返回图片尺寸和最大宽度和总高度
     * @param array $srcPathArr 原图路径数组
     * @return array
     */
    protected function getImagesSize($srcPathArr)
    {
        $images = [];
        $width = $height = 0;
        foreach ($srcPathArr as $k => $srcPath) {
            $images[$k]['path'] = $srcPath;
            $size = getimagesize($srcPath);
            $images[$k]['width'] = $size[0];
            $images[$k]['height'] = $size[1];
            if ($size[0] > $width) {
                $width = $size[0];
            }
            $height += $size[1];
        }
        return [
            'info' => $images,
            'width' => $width,
            'height' => $height
        ];
    }
}
