<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_mooc_audit\local;

/**
 * Guard of the test-data scripts (A-04 of docs/revisao-geral).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\devsite
 */
final class devsite_test extends \basic_testcase {
    /**
     * Sites and expected decisions.
     *
     * @return array
     */
    public static function sites(): array {
        return [
            'lab' => ['http://localhost:8095', true, true],
            'lab without debugging' => ['http://localhost:8095', false, false],
            'loopback' => ['http://127.0.0.1/moodle', true, true],
            'ipv6 loopback' => ['http://[::1]:8080', true, true],
            'dot localhost' => ['https://moodle.localhost', true, true],
            'production with debugging on' => ['https://ava.cefor.ifes.edu.br', true, false],
            'host that only contains localhost' => ['https://localhost.ifes.edu.br', true, false],
            'path that contains localhost' => ['https://ava.ifes.edu.br/localhost', true, false],
        ];
    }

    /**
     * Only a local host with developer debugging is allowed.
     *
     * @dataProvider sites
     * @param string $wwwroot
     * @param bool $debugdeveloper
     * @param bool $expected
     */
    public function test_allowed(string $wwwroot, bool $debugdeveloper, bool $expected): void {
        $this->assertSame($expected, devsite::allowed($wwwroot, $debugdeveloper));
    }
}
