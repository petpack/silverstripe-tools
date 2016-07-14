<?php
class SSTools_StyledEmail extends Email {
	
	/**
	 * CSS
	 * @var string
	 */
	public $CSS = null;
	
	public function send($messageID = null) {
		$this->parseVariables();
		$this->emogrifyBody();
		return parent::send();
	}

	/**
	 * Emogrify the body, if there's any CSS set for the email.
	 * @return void
	 */
	protected function emogrifyBody() {
		if ($this->CSS) {
			$emogrifier = new Emogrifier();
			$html = $this->body;
			$emogrifier->setHTML($html);
			$emogrifier->setCSS($this->CSS);
			$this->body = $emogrifier->emogrify();
		}
	}
}
