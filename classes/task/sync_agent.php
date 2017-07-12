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

/**
 * Template log reader/writer.
 *
 * @package    local_lsr_agent
 * @copyright  2016 Gabriel Pimenta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lsr_agent\task;

defined('MOODLE_INTERNAL') || die();

class sync_agent extends \core\task\scheduled_task
{

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name()
    {
        return get_string('tasksyncagent', 'local_lsr_agent');
    }

    /**
     * Do the job.
     * Throw exceptions on errors (the job will be retried).
     */
    public function execute()
    {
        global $CFG;
        require_once($CFG->dirroot . '/local/lsr_agent/lib.php');


        lsr_log("Iniciando enturmação...", 'info');

//        if (!lsr_check_online()) {
//            lsr_log("Servidor não acessível. Encerrando enturmação\n", 'info');
//            return;
//        }

        $json_users = lsr_get_users();
        foreach ($json_users['results'] as $user) {
            switch ($user['action']) {
                case 'insert':
                    lsr_create_user_roles($user);
                break;
                case 'update':
                break;
                case 'delete':
                break;
            }
        }

        $json_enrols = lsr_get_enrolls();
        foreach ($json_enrols['results'] as $enrol) {
            switch ($enrol['action']) {
                case 'insert':
                    lsr_create_enrol_roles($enrol);
                    break;
                case 'update':
                    break;
                case 'delete':
                    break;
            }
        }

        lsr_log("Fim da enturmação...\n", 'info');
    }
}
