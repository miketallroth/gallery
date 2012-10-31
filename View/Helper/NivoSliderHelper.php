<?php

class NivoSliderHelper extends AppHelper {

	var $helpers = array(
		'Html',
		'Js',
		'Gallery.Gallery',
		);

	function assets($options = array()) {
		$options = Set::merge(array('inline' => false), $options);
		$this->Html->script('/gallery/js/jquery.nivo.slider', false, $options);
		$this->Html->css('/gallery/css/nivo-slider', false, $options);
		$this->Html->css('/gallery/css/nivo-style', false, $options);
	}

	function album($album, $photos) {
		return $this->Html->tag('div', $photos, array(
			'id' => 'gallery-' . $album['Album']['id'],
			));
	}

	function photo($album, $photo) {
		$title = isset($photo['title']) ? $title : '';
		$url = isset($photo['url']) ? : $url = $photo['url'];
		$urlLarge = $this->Html->url('/' . $photo['large']);
		$urlSmall = $this->Html->url('/' . $photo['small']);
		$options = Set::merge(array(
			'rel' => $urlSmall,
			'title' => $title, 
			'url' => $url));
		return $this->Html->image($urlLarge, $options);
	}

	function initialize($album) {
		$config = $this->Gallery->getAlbumJsParams($album);
		$milliSecs = 2000;
		$js = sprintf('setTimeout(function() { $(\'#%s\').nivoSlider(%s); }, %d)',
			'gallery-' . $album['Album']['id'],
			$config,
			$milliSecs
			);
		$this->Js->buffer($js);
	}

}
