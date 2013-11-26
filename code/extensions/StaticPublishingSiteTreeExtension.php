<?php
class StaticPublishingSiteTreeExtension extends DataExtension {

	/**
	 * Queues the urls to be flushed into the queue.
	 */
	private $toUpdate = array();

	/**
	 * Queues the urls to be deleted as part of a next flush operation.
	 */
	private $toDelete = array();

	public function onAfterPublish() {
		$context = array(
			'action' => 'publish'
		);
		$this->collectChanges($context);
		$this->flushChanges();
	}

	public function onBeforeUnpublish() {
		$context = array(
			'action' => 'unpublish'
		);
		$this->collectChanges($context);
	}

	public function onAfterUnpublish() {
		$this->flushChanges();
	}

	/**
	 * Collect all changes for the given context.
	 */
	public function collectChanges($context) {
		increase_time_limit_to();
		increase_memory_limit_to();

		if (is_callable(array($this->owner, 'objectsToUpdate'))) {

			$toUpdate = $this->owner->objectsToUpdate($context);

			foreach ($toUpdate as $object) {
				if (!is_callable(array($this->owner, 'urlsToCache'))) continue;

				$urls = $object->urlsToCache();
				if(!empty($urls)) {
					$this->toUpdate = array_merge(
						$this->toUpdate,
						URLArrayObject::add_object_to_array($urls, $object)
					);
				}

			}
		}

		if (is_callable(array($this->owner, 'objectsToDelete'))) {

			$toDelete = $this->owner->objectsToDelete($context);
			foreach ($toDelete as $object) {
				if (!is_callable(array($this->owner, 'urlsToCache'))) continue;

				$urls = $object->urlsToCache();
				if(!empty($urls)) {
					$this->toDelete = array_merge(
						$this->toDelete,
						URLArrayObject::add_object_to_array($urls, $object)
					);
				}
			}

		}

	}

	/**
	 * Execute URL deletions, enqueue URL updates.
	 */
	public function flushChanges() {
		if(!empty($this->toUpdate)) {
			URLArrayObject::add_urls($this->toUpdate);
			$this->toUpdate = array();
		}

		if(!empty($this->toDelete)) {
			singleton("SiteTree")->unpublishPagesAndStaleCopies($this->toDelete);
			$this->toDelete = array();
		}
	}

	/**
	 * Removes the unpublished page's static cache file as well as its 'stale.html' copy.
	 * Copied from: FilesystemPublisher->unpublishPages($urls)
	 */
	public function unpublishPagesAndStaleCopies($urls) {
		// Detect a numerically indexed arrays
		if (is_numeric(join('', array_keys($urls)))) $urls = $this->owner->urlsToPaths($urls);

		$cacheBaseDir = $this->owner->getDestDir();

		foreach($urls as $url => $path) {
			if (file_exists($cacheBaseDir.'/'.$path)) {
				@unlink($cacheBaseDir.'/'.$path);
			}
			$lastDot = strrpos($path, '.'); //find last dot
			if ($lastDot !== false) {
				$stalePath = substr($path, 0, $lastDot) . '.stale' . substr($path, $lastDot);
				if (file_exists($cacheBaseDir.'/'.$stalePath)) {
					@unlink($cacheBaseDir.'/'.$stalePath);
				}
			}
		}
	}

}
