<?php

require_once $CFG->dirroot . '/course/lib.php';
require_once $CFG->dirroot . '/user/lib.php';
require_once $CFG->dirroot . '/enrol/locallib.php';

define('URL', 'http://localhost:8000');
define('LOG_PATH', '/tmp/logs');
define('TOKEN', '5ee427d3f77296348a6490b81631b3b6f9b65d32');

    #------------------- COURSES

function lsr_create_course($course)
{
    $course['visible'] = 1;
    $course['startdate'] = time();
    return create_course((object) $course);
}

function lsr_create_course_roles($course)
{
    $schema_courses = array(
        'idnumber' => $course['curso_id'],
        'fullname' => $course['curso_name'],
        'shortname' => $course['curso_short_name'],
        'shortname' => $course['curso_short_name'],
        'category' => $course['categoria'],
        'description' => $course['descricao']
    );

    if (!lsr_is_created('course', $course['curso_id'])) {
        lsr_create_course($schema_courses);
        lsr_log("Curso {$course['curso_id']} criado...", 'info');
        lsr_drop_course_queue($course['_id']['$oid']);
    } else {
        lsr_drop_course_queue($course['_id']['$oid']);
        lsr_log("Curso com id {$course['curso_id']} já criado", 'info');
    }
}

function lsr_update_course($course)
{
    global $DB;

    $curso = $DB->get_record('course', array('idnumber' => $course['curso_id']));

    $curso->fullname = $course['curso_name'];
    $curso->shortname = $course['curso_short_name'];
    $curso->description = $course['descricao'];

    update_course($curso);
    lsr_drop_course_queue($course['_id']['$oid']);

    lsr_log("Curso {$course['curso_id']} alterado", 'info');
}

function lsr_delete_course($course)
{
    global $DB;

    $curso = $DB->get_record('course', array('idnumber' => $course['curso_id']));

    if ($curso) {
        $curso->visible = 0;
        update_course($curso);
        lsr_drop_course_queue($course['_id']['$oid']);
        lsr_log("Curso {$course['curso_id']} desabilitado", 'info');
    } else {
        lsr_log("Curso {$course['curso_id']} não encontrado", "error");
        lsr_drop_course_queue($course['_id']['$oid']);
    }
}

function lsr_activate_course($course)
{
    global $DB;

    $curso = $DB->get_record('course', array('idnumber' => $course['curso_id']));

    if ($curso) {
        $curso->visible = 1;
        update_course($curso);
        lsr_drop_course_queue($course['_id']['$oid']);
        lsr_log("Curso {$course['curso_id']} habilitado", 'info');
    } else {
        lsr_log("Curso {$course['curso_id']} não encontrado", "error");
        lsr_drop_course_queue($course['_id']['$oid']);
    }
}

    #------------------- USER

function lsr_create_user($user)
{
    global $CFG;

    $user['confirmed'] = 1;
    $user['mnethostid'] = $CFG->mnet_localhost_id;
    $user['timecreated'] = time();

    return user_create_user((object) $user);
}

function lsr_update_user($aluno)
{
    global $DB;
    
    $user = lsr_email_exists('user', $aluno['email']);

    if ($user) {
        $user->firstname = $aluno['colaborador_nome'];
        $user->lastname = $aluno['sobrenome'];
        $user->email = $aluno['email'];
        $user->password = $aluno['password'];

        user_update_user($user);
    lsr_log("4", "error");
        lsr_log("Usuario {$aluno['aluno_id']} atualizado...", 'info');
        lsr_drop_user_queue($aluno['_id']['$oid']);
    } else {
        lsr_log("Usuario {$aluno['aluno_id']} ou email {$aluno['email']} não encontrado...", 'info');
        lsr_drop_user_queue($aluno['_id']['$oid']);
    }

}


function lsr_activate_user($aluno)
{
    global $DB;

    $user = $DB->get_record('user', array('email' => $aluno['email']));

    if ($user) {
        $user->suspended = 0;
        user_update_user($user);
        lsr_drop_user_queue($aluno['_id']['$oid']);
        lsr_log("Usuario {$aluno['email']} habilitado", 'info');
    } else {
        lsr_log("Usuario {$aluno['email']} não encontrado", "error");
        lsr_drop_user_queue($aluno['_id']['$oid']);
    }
}


function lsr_deactivate_user($aluno)
{
    global $DB;

    $user = $DB->get_record('user', array('email' => $aluno['email']));

    if ($user) {
        $user->suspended = 1;
        user_update_user($user);
        lsr_drop_user_queue($aluno['_id']['$oid']);
        lsr_log("Usuario {$aluno['email']} desabilitado", 'info');
    } else {
        lsr_log("Usuario {$aluno['email']} não encontrado", "error");
        lsr_drop_user_queue($aluno['_id']['$oid']);
    }
}




function lsr_create_user_roles($user)
{
    $schema_users = array(
        'firstname' => $user['colaborador_nome'],
        'lastname' => $user['sobrenome'],
        'email' => $user['email'],
        'username' => $user['cpf'],
        'password' => $user['password']
    );

    if (!lsr_email_exists('user', $user['email'])) {
        lsr_create_user($schema_users);
        lsr_log("Usuario {$user['aluno_id']} criado...", 'info');
        lsr_drop_user_queue($user['_id']['$oid']);
    } else {
        lsr_drop_user_queue($user['_id']['$oid']);
        lsr_log("Usuario {$user['email']} ja existe...", 'info');
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
        'user_idnumber' => $enrol['colaborador_id'],
        'course_idnumber' => $enrol['curso_id'],
        'role_shortname' => 'student'
    );

    if (lsr_is_created('course', $enrol['curso_id'])) {
        if (lsr_is_created('user', $enrol['colaborador_id'])) {
            lsr_enrol_user($schema_enrolls);
            lsr_log("usuario {$enrol['colaborador_id']} matriculado...", 'info');
            lsr_drop_enrol_queue($enrol['_id']['$oid']);
        } else {
            lsr_log("usuario {$enrol['colaborador_id']} nao existe...", 'info');
        }
    } else {
        lsr_log("curso {$enrol['curso_id']} nao existe...", 'info');
    }
}

    #------------------- VERIFICATION

function lsr_is_created($table, $id)
{
    global $DB;
    return $DB->get_record($table, array('idnumber' => $id));
}

function lsr_email_exists($table, $email)
{
    global $DB;
    return $DB->get_record($table, array('email' => $email));
}

function lsr_check_json($json, $keys)
{
    foreach ($keys as $key) {
        if ( !array_key_exists($key, $json) ) {
            return false;
        }
    }
    return true;
}

    #------------------- GETS

function lsr_get_courses()
{
    # CHAVES OBRIGATORIAS NO JSON
    $keys = [
        'curso_id',
        'curso_name',
        'curso_short_name',
        'categoria',
        'descricao',
        'acao'
    ];

    $new_json = [];

    $json = lsr_response_prepare(URL . "/api/queue/curso/get");

    foreach ($json as &$item) {
        if (lsr_check_json($item, $keys)) {
            $new_json[] = $item;
        }
    }
    return $new_json;
}

function lsr_get_users()
{
    # CHAVES OBRIGATORIAS NO JSON
    $keys = [
        'colaborador_nome',
        'sobrenome',
        'email',
        'cpf',
        'password',
        'acao'
    ];

    $new_json = [];


    $json = lsr_response_prepare(URL . "/api/queue/aluno/get");

    foreach ($json as &$item) {
        if (lsr_check_json($item, $keys)) {
            $new_json[] = $item;
        }
    }
    return $new_json;
}

function lsr_get_enrolls()
{
    # CHAVES OBRIGATORIAS NO JSON
    $keys = [
        'colaborador_id',
        'curso_id',
        'acao'
    ];

    $new_json = [];

    $json = lsr_response_prepare(URL . "/api/queue/matricula/get");

    foreach ($json as &$item) {
        if (lsr_check_json($item, $keys)) {
            $new_json[] = $item;
        }
    }

    return $new_json;
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
    lsr_send_request(URL . "/api/queue/aluno/delete/$aluno_id");
}

function lsr_drop_enrol_queue($matricula_id)
{
    lsr_send_request(URL . "/api/queue/matricula/delete/$matricula_id");
}

function lsr_drop_course_queue($course_id)
{
    lsr_send_request(URL . "/api/queue/curso/delete/$course_id");
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
        "X-Auth-Token: $token"
    );

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    return curl_exec($curl);
}

function lsr_check_online($url=URL)
{
    $status_codes = [200, 302];
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
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
