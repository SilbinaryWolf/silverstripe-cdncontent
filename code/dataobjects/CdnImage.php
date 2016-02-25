<?php

/**
 * Subclass that overwrites specific behaviour of Image 
 * 
 * This class type is switched into place when someone saves an image that has
 * the CDNFile extension applied
 *
 * @author <marcus@silverstripe.com.au>
 * @license BSD License http://www.silverstripe.org/bsd-license
 */
class CdnImage extends Image {
    
	public function getFormattedImage($format) {
		$args = func_get_args();
		
		$pointer = $this->obj('CDNFile');
		
		if($this->ID && $this->Filename && $pointer->exists()) {
			$cacheFile = call_user_func_array(array($this, "cacheFilename"), $args);
			
			if(!file_exists(Director::baseFolder()."/".$cacheFile) || isset($_GET['flush'])) {
				call_user_func_array(array($this, "generateFormattedImage"), $args);
			}
			
			$cached = new Image_Cached($cacheFile);
			// Pass through the title so the templates can use it
			$cached->Title = $this->Title;
			// Pass through the parent, to store cached images in correct folder.
			$cached->ParentID = $this->ParentID;
			//Pass through a CanViewType type if we have any so that it can be used for canView checks
			$cached->CanViewType = $this->CanViewType;

			return $cached;
		}

		return call_user_func_array('parent::getFormattedImage', $args);
	}
    
    public function getDimensions($dim = "string") {
        $pointer = $this->obj('CDNFile');
        if($this->ID && $this->Filename && $pointer->exists()) {
            $this->ensureLocalFile();
        }
        
        if ($this->localFileExists()) {
            return parent::getDimensions($dim);
        }
    }

	/**
	 * Replaces the Preview Image and Link with secured links if the file is secured.
	 * @return FieldList
	 */
	public function getCMSFields() {
		$fields = parent::getCMSFields();

		$previewField = new LiteralField("SecureImageFull",
				"<img id='thumbnailImage' class='thumbnail-preview' src='".$this->Link()."' alt='Secured File' />\n"
		);

		$url = $this->getSecureURL(300);// 5 minute link expire
		$link = ReadonlyField::create('CDNUrl', 'CDN link',  sprintf('<a href="%s" target="_blank">%s</a>', $url, $url));
		$link->setRightTitle('This link expires in five minutes from: '. date("Y-m-d H:i:s"));
		$link->dontEscape = true;

		if ($top = $fields->fieldByName('Root.Main.FilePreview')) {
			$field = $top->fieldByName('FilePreviewImage');
			$field->insertBefore($previewField, 'ImageFull');
			$field->removeByName('ImageFull');
			$top->replaceField('CDNUrl', $link);
		}

		return $fields;
	}
}
