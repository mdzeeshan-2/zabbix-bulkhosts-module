<?php

namespace Modules\BulkHosts\Helpers\Html;

use CTag;

class RawContent extends CTag {

	public function setRawContent($value) {
		$this->items = [$value];

		return $this;
	}
}
