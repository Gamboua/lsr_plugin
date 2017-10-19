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

        if (!lsr_check_online()) {
            lsr_log("Servidor não acessível. Encerrando enturmação", 'info');
            return;
        }


        $json_courses = lsr_get_courses();
        $json_users = lsr_get_users();
        $json_enrolls = lsr_get_enrolls();

        $count = 0;

        lsr_log("Iniciando processamento de " .count($json_courses). " cursos", 'info');
        foreach ($json_courses as $course) {
            switch ($course['acao']) {
                case 'incluir':
                    lsr_create_course_roles($course);
                    $count++;
                break;
                case 'editar':
                    lsr_update_course($course);
                    $count++;
                break;
                case 'desativar':
                    lsr_delete_course($course);
                    $count++;
                break;
                case 'ativar':
                    lsr_activate_course($course);
                    $count++;
                break;
                default:
                    lsr_log("Ação '{$course['acao']}' para CURSO não encontrada", 'error');
            }
        }
        lsr_log("$count registros foram processados", 'info');

        $count = 0;

        lsr_log("Iniciando processamento de ". count($json_users) . " alunos", 'info');
        foreach ($json_users as $user) {
            switch ($user['acao']) {
                case 'incluir':
                    lsr_create_user_roles($user);
                    $count++;
                break;
                case 'insert':
                    lsr_create_user_roles($user);
                    $count++;
                break;
                case 'editar':
                    lsr_update_user($user);
                    $count++;
                break;
                case 'ativar':
                    lsr_activate_user($user);
                    $count++;
                break;
                case 'desativar':
                    lsr_deactivate_user($user);
                    $count++;
                break;
                default:
                    lsr_log("Ação '{$user['acao']}' para ALUNO não encontrada", 'error');
            }
        }
        lsr_log("$count registros foram processados", 'info');

        $count = 0;

        lsr_log("Iniciando processamento de ". count($json_enrolls) . " matriculas", 'info');
        foreach ($json_enrolls as $enrol) {
            switch ($enrol['acao']) {
                case 'incluir':
                    lsr_create_enrol_roles($enrol);
                    $count++;
                break;
                default:
                    lsr_log("Ação '{$enrol['acao']}' para MATRICULA não encontrada", 'error');
            }
        }
        lsr_log("$count registros foram processadas", 'info');

        lsr_log("Fim da enturmação...\n", 'info');
    }
}
