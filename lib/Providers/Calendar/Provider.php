<?php

namespace OCA\JMAPC\Providers\Calendar;

use OCP\Calendar\ICalendarProvider as ICalendarProvider1;
use OCA\DAV\CalDAV\Integration\ExternalCalendar;
use OCA\DAV\CalDAV\Integration\ICalendarProvider as ICalendarProvider2;

use OCA\JMAPC\AppInfo\Application;
use OCA\JMAPC\Store\EventStore;
use OCA\JMAPC\Store\TaskStore;

class Provider implements ICalendarProvider1, ICalendarProvider2 {

	private EventStore $_EventStore;
	private TaskStore $_TaskStore;

	public function __construct(EventStore $EventStore, TaskStore $TaskStore) {
		$this->_EventStore = $EventStore;
		$this->_TaskStore = $TaskStore;
	}

	/**
	 * @inheritDoc
	 */
	public function getAppId(): string {
		return Application::APP_ID;
	}

	/**
	 * @inheritDoc
	 */
	public function getCalendars(string $principalUri, array $calendarUris = []): array {
		return [];
	}

	/**
	 * @inheritDoc
	 */
	public function fetchAllForCalendarHome(string $principalUri): array {
		
		// construct collection objects list
		$list = [];
		// retrieve collection(s)
		$collections = $this->_EventStore->collectionListByUser(substr($principalUri, 17), 'EC');
		// add collections to list
		foreach ($collections as $entry) {
			$list[] = new EventCollection($this->_EventStore, $entry);
		}
		// retrieve collection(s)
		$collections = $this->_TaskStore->collectionListByUser(substr($principalUri, 17), 'TC');
		// add collections to list
		foreach ($collections as $entry) {
			$list[] = new TaskCollection($this->_TaskStore, $entry);
		}
		// return collection objects list
		return $list;

	}

	/**
	 * @inheritDoc
	 */
	public function hasCalendarInCalendarHome(string $principalUri, string $calendarUri): bool {

		return $this->_EventStore->collectionConfirmByUUID(substr($principalUri, 17), $calendarUri);

	}

	/**
	 * @inheritDoc
	 */
	public function getCalendarInCalendarHome(string $principalUri, string $calendarUri): ?ExternalCalendar {

		$entry = $this->_EventStore->collectionFetchByUUID(substr($principalUri, 17), $calendarUri);

		if (isset($entry)) {
			if ($entry->getType() == 'EC') {
				return new EventCollection($this->_EventStore, $entry);
			}
			elseif ($entry->getType() == 'TC') {
				return new TaskCollection($this->_TaskStore, $entry);
			}
		}
		else {
			return null;
		}

	}

}
