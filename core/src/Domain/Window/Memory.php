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

	public function windowState($_cmdId);

	public function setWindowState($_cmdId, $_state);

	public function closedAt($_cmdId);

	public function setClosedAt($_cmdId, $_datetime);

	public function openSince();

	public function setOpenSince($_timestamp);

	public function alertSent();

	public function setAlertSent($_sent);
}
