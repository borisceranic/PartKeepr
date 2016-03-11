<?php
namespace PartKeepr\Image;

use PartKeepr\Util\Configuration;

class ImageRenderer {
	private $image = null;

	private $resamplingAlgorithm = IMG_BICUBIC;

	public function __construct (RenderableImage $image) {
		$this->image = $image;
	}
	
	/**
	 * Renders and outputs the "image not found" image.
	 * 
	 * Sends the header and immediately outputs the image.
	 * 
	 * @param int $w Width of the image
	 * @param int $h Height of the image
	 */
	public static function outputRenderNotFoundImage ($w, $h) {
		$image = \imagecreate($w, $h);
		$white = \imagecolorallocate($image, 255,255,255);
		$black = \imagecolorallocate($image, 0,0,0);
		
		header("Content-Type: image/png");
		
		$w = $w-1;
		$h = $h-1;
		\imagefill($image, 0,0, $white);
		
		/* Draw the X */
		\imageline($image, 0,0,$w,$h, $black);
		\imageline($image, $w,0,0,$h, $black);
		\imagepng($image);
	}
	
	/**
	 * Scales the image to fit within the given size.
	 *
	 * @param string $outputFile The target file
	 * @param int $w The width
	 * @param int $h The height
	 * @param boolean $padding If true, pad the output image to the given size (transparent background).
	 * @return string The path to the scaled file
	 */
	public function fitWithin ($outputFile, $w, $h, $padding = false) {
		$image = $this->getImageResource();

		list($imageWidth, $imageHeight) = getimagesize($this->image->getFilename());

		$sourceAspectRatio = $imageWidth / $imageHeight;
		$targetAspectRatio = $w / $h;
	
		$filter = \Imagick::FILTER_UNDEFINED;
		$blur = 1;
	
		$targetHeight = $h;
		$targetWidth = $w;
	
		if ($sourceAspectRatio < $targetAspectRatio) {
			$targetWidth = $h * $sourceAspectRatio;
			$image = \imagescale($image, $targetWidth, $h, $this->resamplingAlgorithm);
		} else {
			$targetHeight = $w / $sourceAspectRatio;
			$image = \imagescale($image, $w, $targetHeight, $this->resamplingAlgorithm);
		}
	
		if ($padding) {
			$posX = intval(($w - $targetWidth) / 2);
			$posY = intval(($h - $targetHeight) / 2);

			$borderWidth = $posX * 2;
			$borderHeight = $posY * 2;

			$newImage = \imagecreate($w + $borderWidth, $h + $borderHeight);

			\imagecopy(
				$_dst_image = $newImage,
				$_src_image = $image,

				// define where to place copied section
				$_dst_x = $posX,
				$_dst_y = $posY,

				// define what section to copy from source image
				$_src_x = 0,
				$_src_y = 0,

				$_src_w = $w,
				$_src_h = $h
			);

			$image = $newImage;
		}

		$this->writeImage($image, $outputFile);

		return $outputFile;
	}
	
	/**
	 * Scales the image to fit exactly within the given size.
	 *
	 * This method ensures that no blank space is in the output image,
	 * and that the output image is exactly the width and height specified.
	 *
	 * @param string $outputFile The output file
	 * @param int $w The width
	 * @param int $h The height
	 * @return string The path to the scaled file
	 */
	public function fitWithinExact ($outputFile, $w, $h) {
		$image = $this->getImageResource();

		list($imageWidth, $imageHeight) = \getimagesize($this->image->getFilename());

		$targetAspectRatio = $w / $h;

		if ($sourceAspectRatio < $targetAspectRatio) {
			$targetHeight = $w / $sourceAspectRatio;
			$image = \imagescale($image, $w, $targetHeight, $this->resamplingAlgorithm);
		} else {
			$targetWidth = $h * $sourceAspectRatio;
			$image = \imagescale($image, $targetWidth, $h, $this->resamplingAlgorithm);
		}

		$offsetX = intval(($imageWidth - $w)/2);
		$offsetY = intval(($imageHeight - $h)/2);

		$newImage = \imagecreate($w, $h);

		\imagecopy(
			$_dst_image = $newImage,
			$_src_image = $image,

			// define where to place copied section
			$_dst_x = 0,
			$_dst_y = 0,

			// define what section to copy from source image
			$_src_x = $offsetX,
			$_src_y = $offsetY,

			$_src_w = $w,
			$_src_h = $h
		);

		$this->writeImage($newImage, $outputFile);

		return $outputFile;
	}
	
	/**
	 * Scales the image to a specific width and height
	 *
	 * @param string $outputFile The output file
	 * @param int $w The width
	 * @param int $h The height
	 * @return string The path to the scaled file
	 */
	public function scaleTo ($outputFile, $w, $h) {
		$image = $this->getImageResource();
		$image = \imagescale($image, $w, $h, $this->resamplingAlgorithm);

		$this->writeImage($image, $outputFile);

		return $outputFile;
	}

	/**
	 * Scales the image to a specific width and height
	 *
	 * @return resource Image resource consumable by GD2 image functions
	 */
	private function getImageResource () {
		$imageContents = \file_get_contents($this->image->getFilename());
		return \imagecreatefromstring($imageContents);
	}

	private function writeImage ($imageResource, $filename) {
		$type = $this->getImageType();
		switch ($type) {
			case IMAGETYPE_GIF:
				\imagegif($imageResource, $filename);
				break;
			case IMAGETYPE_JPEG:
				\imagejpeg($imageResource, $filename);
				break;
			case IMAGETYPE_PNG:
				\imagepng($imageResource, $filename);
				break;
			case IMAGETYPE_WBMP:
				\imagewbmp($imageResource, $filename);
				break;
			case IMAGETYPE_XBM:
				\imagexbm($imageResource, $filename);
				break;
			default:
				throw new \Exception('Unsupported image type: '.\image_type_to_mime_type($type));
				break;
		}
	}

	/**
	 * Returns numeric image type usable by image_type_to_mime_type()
	 *
	 * @return int Image type consumable by GD2 image functions
	 */
	private function getImageType () {
		list($w, $h, $type) = \getimagesize($this->image->getFilename());

		return $type;
	}
}
