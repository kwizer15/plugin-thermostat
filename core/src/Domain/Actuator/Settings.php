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

namespace Jeedom\Plugin\Thermostat\Domain\Actuator;

interface Settings {

	/**
	 * @return string
	 */
	public function allowMode();

	/**
	 * @return list<array{cmd: string, options?: array<string, scalar|null>}>
	 */
	public function heatingActions();

	/**
	 * @return list<array{cmd: string, options?: array<string, scalar|null>}>
	 */
	public function coolingActions();

	/**
	 * @return list<array{cmd: string, options?: array<string, scalar|null>}>
	 */
	public function stoppingActions();

	/**
	 * @return list<array{cmd: string, options?: array<string, scalar|null>}>
	 */
	public function orderChangeActions();

	/**
	 * @return list<array{cmd: string, options?: array<string, scalar|null>}>
	 */
	public function failureActions();

	/**
	 * @return list<array{cmd: string, options?: array<string, scalar|null>}>
	 */
	public function failureActuatorActions();

	/**
	 * @return list<array{name: string, actions: list<array{cmd: string, options?: array<string, scalar|null>}>}>
	 */
	public function modes();
}
