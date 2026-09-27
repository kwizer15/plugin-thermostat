<?php

/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

namespace Jeedom\Plugin\Thermostat\Domain\Window;

interface Memory {

	/**
	 * @param int|string $_cmdId
	 * @return int|float|string
	 */
	public function windowState($_cmdId);

	/**
	 * @param int|string $_cmdId
	 * @param int $_state
	 * @return void
	 */
	public function setWindowState($_cmdId, $_state);

	/**
	 * @param int|string $_cmdId
	 * @return string
	 */
	public function closedAt($_cmdId);

	/**
	 * @param int|string $_cmdId
	 * @param string $_datetime
	 * @return void
	 */
	public function setClosedAt($_cmdId, $_datetime);

	/**
	 * @param int|string $_cmdId
	 * @return string
	 */
	public function openedAt($_cmdId);

	/**
	 * @param int|string $_cmdId
	 * @param string $_datetime
	 * @return void
	 */
	public function setOpenedAt($_cmdId, $_datetime);

	/**
	 * @return int|float|string
	 */
	public function openSince();

	/**
	 * @param int $_timestamp
	 * @return void
	 */
	public function setOpenSince($_timestamp);

	/**
	 * @return int|float|string
	 */
	public function alertSent();

	/**
	 * @param int $_sent
	 * @return void
	 */
	public function setAlertSent($_sent);
}
