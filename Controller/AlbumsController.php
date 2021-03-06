<?php

App::uses('GalleryAppController', 'Gallery.Controller');
App::uses('Galleries', 'Gallery.Lib');

/**
 * Albums Controller
 *
 * PHP version 5
 *
 * @category Controller
 * @package  Croogo
 * @version  1.3
 * @author   Edinei L. Cipriani <phpedinei@gmail.com>
 * @license  http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link     http://www.demoveo.com
 */
class AlbumsController extends GalleryAppController {

	public $components = array(
		'Search.Prg' => array(
			'presetForm' => array(
				'paramType' => 'querystring',
			),
			'commonProcess' => array(
				'paramType' => 'querystring',
			),
		),
	);

	public $presetVars = array(
		'title' => array('type' => 'like'),
		'description' => array('type' => 'like'),
	);

	public function beforeFilter() {
		$this->jslibs = Galleries::activeLibs();
		parent::beforeFilter();

		$noCsrf = array('admin_upload_photo', 'admin_delete_photo', 'admin_toggle');
		if (in_array($this->action, $noCsrf) && $this->request->is('ajax')) {
			$this->Security->csrfCheck = false;
		}
	}

/**
 * Toggle Album status
 *
 * @param string $id Album id
 * @param integer $status Current Album status
 * @return void
 */
	public function admin_toggle($id = null, $status = null) {
		$this->Croogo->fieldToggle($this->{$this->modelClass}, $id, $status);
	}

	public function admin_index() {
		$title_for_layout = __d('gallery','Albums');
		$searchFields = array(
			'title',
			'description' => array(
				'type' => 'text',
			),
		);

		$this->Prg->commonProcess();

		$this->Album->recursive = 0;
		$this->paginate = array(
			'limit' => Configure::read('Gallery.album_limit_pagination'),
			'order' => 'Album.position',
			'conditions' => $this->Album->parseCriteria($this->request->query),
		);
		$albums = $this->paginate();
		$this->set(compact('title_for_layout', 'albums', 'searchFields'));
	}

	public function admin_add() {
		if (!empty($this->request->data)) {
			$this->Album->create();
			if (empty($this->request->data['Album']['slug'])){
				$this->request->data['Album']['slug'] = Inflector::slug($this->request->data['Album']['title']);
			}

			$this->Album->recursive = -1;
			$position = $this->Album->find('all',array(
				'fields' => 'MAX(Album.position) as position'
			));

			$this->request->data['Album']['position'] = $position[0][0]['position'] + 1;

			if ($this->Album->save($this->request->data)) {
				$this->Session->setFlash(__d('gallery', 'Album is saved.'), 'flash', array('class' => 'success'));
				$this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash(__d('gallery', 'Album could not be saved. Please try again.'), 'flash', array('class' => 'error'));
			}
		}
		$this->set('types', $this->jslibs);
	}

	function admin_edit($id = null) {
		if (!$id) {
			$this->Session->setFlash(__d('gallery', 'Invalid album.'), 'flash');
			$this->redirect(array('action' => 'index'));
		}
		if (!empty($this->request->data)) {
			if ($this->Album->save($this->request->data)) {
				$this->Session->setFlash(__d('gallery', 'Album has been saved.'), 'flash', array('class' => 'success'));
				$this->Croogo->redirect(array('action' => 'edit', $id));
			} else {
				$this->Session->setFlash(__d('gallery', 'Album could not be saved. Please try again.'), 'flash', array('class' => 'error'));
			}
		}

		$this->request->data = $this->Album->read(null, $id);
		$this->set('types', $this->jslibs);
	}

	function admin_delete($id = null) {
		if (!$id) {
			$this->Session->setFlash(__d('gallery','Invalid ID for album.'), 'flash', array('class' => 'error'));
			$this->redirect(array('action' => 'index'));
		} else {
			$ssluga = $this->Album->findById($id);
			$sslug = $ssluga['Album']['slug'];

			$dir  = WWW_ROOT . 'img' . DS . $sslug;
		}
		if ($this->Album->delete($id, true)) {
			$this->Session->setFlash(__d('gallery', 'Album is deleted, and whole directory with images.'), 'flash', array('class' => 'error'));
			$this->redirect(array('action' => 'index'));
		}
		$this->render(false);
	}

	public function index() {
		$this->set('title_for_layout',__d('gallery',"Albums"));

		$this->Album->recursive = -1;
		$this->Album->Behaviors->attach('Containable');
		$this->paginate = array(
			'conditions' => array('Album.status' => CroogoStatus::PUBLISHED),
			'contain' => array(
				'Photo' => array(
					'ThumbnailAsset', 'LargeAsset', 'OriginalAsset',
				),
			),
			'limit' => Configure::read('Gallery.album_limit_pagination'),
			'order' => 'Album.position ASC',
		);

		$this->set('albums', $this->paginate());
	}

	public function view($slug = null) {
		if (!$slug) {
			$this->Session->setFlash(__d('gallery', 'Invalid album. Please try again.'), array('class' => 'error'));
			$this->redirect(array('action' => 'index'));
		}

		$album = $this->Album->find('photos', array(
			'slug' => $slug
		));

		if (isset($this->params['requested'])) {
			return $album;
		}

		if (!count($album)) {
			$this->Session->setFlash(__d('gallery', 'Invalid album. Please try again.'), 'flash', array('class' => 'error'));
			$this->redirect(array('action' => 'index'));
		}

		if ($album['Album']['status'] == CroogoStatus::UNPUBLISHED) {
			throw new NotFoundException(__d('gallery', 'Invalid album. Please try again.'));
		}

		$this->set('title_for_layout', __d('gallery', 'Album %s', $album['Album']['title']));
		$this->set(compact('album'));
	}

	public function admin_upload($id = null) {
		if (!$id) {
			$this->Session->setFlash(__d('gallery', 'Invalid album. Please try again.'), 'flash', array('class' => 'error'));
			$this->redirect(array('action' => 'index'));
		}
		$this->set('title_for_layout',__d('gallery',"Manage your photos in album"));

		$album = $this->Album->find('first', array(
			'contain' => array(
				'Photo' => array(
					'OriginalAsset', 'LargeAsset', 'ThumbnailAsset',
				),
			),
			'conditions' => array(
				'Album.id' => $id
			),
			'recursive' => -1,
		));
		if (isset($album['Photo'])) {
			$photos = Hash::sort($album['Photo'], '{n}.AlbumsPhoto.weight', 'asc');
			$album['Photo'] = $photos;
		}

		$this->set('album', $album);
	}

	public function admin_upload_photo($id = null) {
		set_time_limit ( 240 ) ;

		$this->layout = 'ajax';
		$this->render(false);
		Configure::write('debug', 0);

		$this->request->data['Photo']['status'] = CroogoStatus::PUBLISHED;
		$this->request->data['Album'][] = array('album_id' => $id, 'master' => true);

		$slug = $this->Album->field('slug', array('Album.id' => $id));
		$this->Album->Photo->setTargetDirectory($slug);
		$data = $this->Album->Photo->create();
		$this->Album->Photo->save($this->request->data);

		echo json_encode($this->Album->Photo->findById($this->Album->Photo->id));
	}

	public function admin_reset_weight($id = null) {
		$this->Album->id = $id;
		if ($this->Album->AlbumsPhoto->resetWeights()) {
			$this->Session->setFlash(__d('gallery', 'Weight have been reset'), 'flash', array('class' => 'success'));
		} else {
			$this->Session->setFlash(__d('gallery', 'Unable to reset weight'), 'flash', array('class' => 'error'));
		}
		$this->redirect($this->referer());
	}

	public function admin_delete_photo($id = null) {
		$this->layout = 'ajax';
		$this->autoRender = false;

		if (!$id) {
			echo json_encode(array('status' => CroogoStatus::UNPUBLISHED, 'msg' => __d('gallery','Invalid photo. Please try again.'))); exit();
		}

		if ($this->Album->Photo->delete($id)) {
			echo json_encode(array('status' => CroogoStatus::PUBLISHED)); exit();
		} else {
			echo json_encode(array('status' => CroogoStatus::UNPUBLISHED,  'msg' => __d('gallery','Problem to remove photo. Please try again.'))); exit();
		}
	}

}
