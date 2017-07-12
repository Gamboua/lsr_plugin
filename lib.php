<?php

require_once $CFG->dirroot . '/course/lib.php';
require_once $CFG->dirroot . '/user/lib.php';
require_once $CFG->dirroot . '/enrol/locallib.php';
require_once $CFG->dirroot . '/lib/datalib.php';
require_once $CFG->dirroot . '/lib/gradelib.php';

define('URL', 'http://localhost:8000');
define('LOG_PATH', '/tmp/logs');
define('TOKEN', '5ee427d3f77296348a6490b81631b3b6f9b65d32');

    #------------------- USER

function lsr_create_user($user)
{
    global $CFG;

    $user['confirmed'] = 1;
    $user['mnethostid'] = $CFG->mnet_localhost_id;
    $user['timecreated'] = time();
    $user['auth'] = 'ws';
    return user_create_user((object) $user);
}

function lsr_create_user_roles($user)
{
    if (!lsr_is_created('user', $user['idnumber']) && !lsr_email_exists('user', $user['email'])) {

        $schema_users = array(
            'firstname' => $user['nome'],
            'lastname' => $user['sobrenome'],
            'email' => $user['email'],
            'idnumber' => $user['idnumber'],
            'username' => $user['cpf']
        );
        lsr_create_user($schema_users);
        lsr_log("Usuario {$user['nome']} {$user['sobrenome']} criado...", 'info');
        lsr_drop_user_queue($user['id']);
    } else {
        lsr_drop_user_queue($user['id']);
        lsr_log("Usuario {$user['idnumber']} ou email {$user['email']} ja existe...", 'info');
    }
}

    #------------------- ENROLL

function lsr_enrol_user($content)
{
    global $PAGE, $DB;

    $course = $DB->get_record('course', array('idnumber' => $content['course_idnumber']), '*', MUST_EXIST);
    $user = $DB->get_record('user', array('idnumber' => $content['user_idnumber']), '*', MUST_EXIST);
    $role = $DB->get_record('role', array('shortname' => $content['role_shortname']), '*', MUST_EXIST);

    if ($course && $user && $role) {
        $manager = new course_enrolment_manager($PAGE, $course);

        $instances = $manager->get_enrolment_instances();
        //find the manual one
        foreach ($instances as $instance) {
            if ($instance->enrol == 'manual') {
                break;
            }
        }

        $plugins = $manager->get_enrolment_plugins();
        $plugin = $plugins['manual'];

        $today = time();
        $today = make_timestamp(date('Y', $today), date('m', $today), date('d', $today), 0, 0, 0);

        $plugin->enrol_user($instance, $user->id, $role->id, $today, 0);
    }
}

function lsr_create_enrol_roles($enrol)
{
    $schema_enrolls = array(
        'user_idnumber' => $enrol['aluno'],
        'course_idnumber' => $enrol['curso'],
        'role_shortname' => 'student'
    );

    if (lsr_course_is_created($enrol['curso'])) {
        if (lsr_user_is_created($enrol['aluno'])) {
            lsr_enrol_user($schema_enrolls);
            lsr_log("Usuário {$enrol['aluno']} matriculado...", 'info');
            lsr_drop_enrol_queue($enrol['id']);
        } else {
            lsr_log("Usuário {$enrol['aluno']} nao existe...", 'info');
        }
    } else {
        lsr_log("Não existe curso com o id {$enrol['curso']}!", 'info');
    }
}

    #------------------- VERIFICATION

function lsr_is_created($table, $id)
{
    global $DB;
    return $DB->get_record($table, array('idnumber' => $id));
}

function lsr_course_is_created($id)
{
    global $DB;
    return $DB->get_record('course', array('id' => $id));
}

function lsr_user_is_created($id)
{
    global $DB;
    return $DB->get_record('user', array('idnumber' => $id));
}

function lsr_email_exists($table, $email)
{
    global $DB;
    return $DB->get_record($table, array('email' => $email));
}

    #------------------- GETS

function lsr_get_users()
{
    return lsr_response_prepare(URL . "/api/moodle/aluno/");
}

function lsr_get_enrolls()
{
    return lsr_response_prepare(URL . "/api/moodle/matricula/");
}

function lsr_response_prepare($url)
{
    if (lsr_check_online($url)) {
        $response = lsr_send_request($url);
        $response = json_decode($response, true);

        if (array_key_exists('erro', $response)) {
            lsr_log("Falha na busca de resultados: " . json_encode($response), 'error');
            return [];
        } else {
            lsr_log("JSON em '$url' obtido com sucesso", 'info');
            return $response;
        }
    } else {
        lsr_log("URL '$url' não acessível", 'error');
        return [];
    }
}

    #------------------- DROPS

function lsr_drop_user_queue($aluno_id)
{
    lsr_drop_request(URL . "/api/moodle/aluno/$aluno_id/");
}

function lsr_drop_enrol_queue($enrol_id)
{
    lsr_drop_request(URL . "/api/moodle/matricula/$enrol_id/");
}

    #------------------- REQUEST

/**
 * Envia requisição com token para o LRS
 *
 * @param $url
 * @param string $token
 * @return mixed
 */
function lsr_send_request($url, $token=TOKEN)
{
    $headers = array(
        "Authorization: Token $token"
    );

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    return curl_exec($curl);
}

function lsr_drop_request($url, $token=TOKEN)
{
    $headers = array(
        "Authorization: Token $token"
    );

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');

    return curl_exec($curl);
}

function lsr_check_online($token=TOKEN, $url=URL)
{
    $status_codes = [200, 302];
    $headers = array(
        "Authorization: Token $token"
    );
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_exec($curl);

    $info = curl_getinfo($curl);
    return in_array($info['http_code'], $status_codes) ? true : false ;
}

    #------------------- LOG

function lsr_log($msg, $type)
{
    $inicio = date('[H:i:s - d/m/Y]') ;

    $type = $type == 'error' ? '[ERROR]' : '[INFO]';

    file_put_contents(LOG_PATH, $type . ' ' . $inicio . ' - ' . $msg . "\n", FILE_APPEND);
}
