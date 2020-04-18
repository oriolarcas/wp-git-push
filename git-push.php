<?php
/*
Plugin Name: Git Push
Author: lamaquinadeturing
Author URI: https://lamaquinadeturing.su/
Description: Commit and push the local Git repository to publish your static website.
Version: 0.1
Text Domain: git-push
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

function git_push_admin_init() {
   wp_enqueue_style('fontawesome',
                    'https://use.fontawesome.com/releases/v5.8.1/css/all.css',
                    '', '5.8.1', 'all');
}

add_action('admin_init', 'git_push_admin_init');

function git_push_admin_menu() {
    add_menu_page(
        __( 'Git push', 'git-push' ),
        __( 'Git push', 'git-push' ),
        'publish_posts',
        'sample-page',
        'git_push_page_contents',
        'dashicons-share',
        3
    );
}

add_action( 'admin_menu', 'git_push_admin_menu' );

/*******************************************************************************
 WP2Static
 ******************************************************************************/

function is_wp2static_active() {
    return class_exists('WP2Static_Controller');
}

function get_wp2static_folder() {
    $d = WP2Static_Controller::getInstance()->options->getOption("selected_deployment_option");
    if ($d != "folder")
        return false;
    return WP2Static_Controller::getInstance()->options->getOption("targetFolder");
}

/*******************************************************************************
 Git
 ******************************************************************************/

function git_status($git_directory) {
    exec('cd '.$git_directory.' && git status --porcelain', $git_output, $git_code);
    return array($git_output, $git_code);
}

function git_unpushed_commits($git_directory) {
    exec('cd '.$git_directory.' && git rev-list --count origin/master..HEAD',
         $git_output, $git_code);
    return array($git_output, $git_code);
}

function exec_command($cmd, $cwd) {
    $descriptorspec = array(
        0 => array("pipe", "r"),  // stdin
        1 => array("pipe", "w"),  // stdout
        2 => array("pipe", "w"),  // stderr
    );
    $pipes = array();
    $process = proc_open($cmd, $descriptorspec, $pipes, $cwd);
    
    if (!is_resource($process))
        return false;
    
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $code = proc_close($process);
    
    return array(
        "stdout" => $stdout,
        "stderr" => $stderr,
        "code" => $code);
}

function git_push($git_directory) {
    $git_push = exec_command('git push origin master', $git_directory);
    
    if ($git_push === false)
        return "proc_open('git push') failed";

    if ($git_push["code"] != 0)
        return $git_push["stderr"];
        
    return null;
}

function git_commit_and_push($git_directory, $commit_msg, $commit_user, $commit_email, $commit_all) {
    if ($commit_all) {
        $git_add = exec_command('git add .', $git_directory);

        if ($git_add === false)
            return "proc_open('git add') failed";

        if ($git_add["code"] != 0)
            return $git_add["stderr"];
    }

    $commit_msg = escapeshellarg($commit_msg);
    $commit_user = escapeshellarg($commit_user);
    $commit_email = escapeshellarg($commit_email);
    $cmd = 'git -c user.name='.$commit_user.' -c user.email='.$commit_email.' commit --all -m '.$commit_msg;
    
    $git_commit = exec_command($cmd, $git_directory);
    
    if ($git_commit === false)
        return "proc_open('git commit') failed";

    if ($git_commit["code"] != 0)
        return $git_add["stderr"];
    
    return git_push($git_directory);
}

function get_git_status_icon($s, $f = "") {
    switch ($s) {
    case "M":
        return '<i class="fas fa-edit"></i> ';
    case "D":
        return '<i class="fas fa-trash-alt"></i> ';
    case "A":
        return '<i class="fas fa-plus-square"></i> ';
    case "?":
        if ($f && is_dir($f))
            return '<i class="fas fa-folder"></i> ';
        return '<i class="fas fa-file"></i> ';
    case "R":
        return '<i class="fas fa-i-cursor"></i> ';
    case " ":
        return '';
    default:
        return '<i class="fas fa-question"></i> ';
    }
}


/*******************************************************************************
 Process post
 ******************************************************************************/

function process_post_commit($git_directory) {
    $commit_message = filter_input(INPUT_POST, "commit_message");
    if (!$commit_message) {
        render_commit_error("Commit message cannot be empty.");
        return false;
    }
    
    $commit_user = filter_input(INPUT_POST, "commit_user");
    if (!$commit_user) {
        render_commit_error("Name cannot be empty.");
        return false;
    }
    
    $commit_email = filter_input(INPUT_POST, "commit_email");
    if (!$commit_email) {
        render_commit_error("Email cannot be empty.");
        return false;
    }
    
    $commit_all = filter_input(INPUT_POST, "commit_all", FILTER_VALIDATE_BOOLEAN);
    if (is_null($commit_all)) {
        $commit_all = false;
    }
    
    $commit_result = git_commit_and_push($git_directory, $commit_message,
        $commit_user, $commit_email, $commit_all);
    if (!is_null($commit_result)) {
        render_commit_error("Error committing and pushing: ".esc_html($commit_result));
        return false;
    }
    
    return true;
}

function process_post_push($git_directory) {
    $push_result = git_commitpush($git_directory);
    if (!is_null($push_result)) {
        render_commit_error("Error pushing: ".esc_html($push_result));
        return false;
    }
    
    return true;
}

function process_post_options($git_directory) {
    if (filter_input(INPUT_POST, "commit")) {
        if (!process_post_commit($git_directory))
            return;
    } else if (filter_input(INPUT_POST, "push")) {
        if (!process_post_push($git_directory))
            return;
    } else {
        /* nothing to do */
        return;
    }

?>
    <p><i class="fas fa-check-circle"></i> Changes published correctly.</p>
<?php
}

function render_commit_error($msg) {
?>
    <p><i class="fas fa-exclamation-circle"></i> <?php echo $msg; ?></p>
<?php
}

function render_nothing_to_commit() {
?>
    <p><i class="fas fa-info-circle"></i> Nothing to commit or push.</p>
<?php
}

/*******************************************************************************
 Options page
 ******************************************************************************/

function render_commit_status($git_status, $git_unpushed_count, $git_directory) {
    $git_commit_count = count($git_status);
?>
        <tr>
            <th scope="row">Status</th>
            <td>
                <div style="max-height:200px; overflow-y: scroll;">
                    <ul class="fa-ul">
<?php
    foreach ($git_status as $f) {
        echo '<li>';
        $index = $f[0];
        $working = $f[1];
        if ($index != " " && $index != "?")
            echo get_git_status_icon($index);
        echo get_git_status_icon($working, $git_directory.'/'.substr($f, 3));
        echo esc_html(substr($f, 3))."</li>\n";
    }
?>
                    </ul>
                </div>
                <p class="description"><?php echo $git_commit_count; ?> files with changes, <?php echo $git_unpushed_count; ?> unpushed commits in <?php echo $git_directory; ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="commit_message">Commit message</label></th>
            <td><input name="commit_message" id="commit_message" class="regular-text" type="text" value="Website update"></td>
        </tr>
<?php
    $wpu = wp_get_current_user();
    $user_name = $wpu->display_name;
    $user_email = $wpu->user_email;
?>
        <tr>
            <th scope="row"><label for="commit_user">Name</label></th>
            <td><input name="commit_user" id="commit_user" class="regular-text" type="text" value="<?php echo $user_name; ?>"></td>
        </tr>
        <tr>
            <th scope="row"><label for="commit_email">Email</label></th>
            <td><input name="commit_email" id="commit_email" class="regular-text" type="text" value="<?php echo $user_email; ?>"></td>
        </tr>
        <tr>
            <th scope="row">Commit all</th>
            <td>
                <fieldset>
                    <label for="commit_all">
                        <input id="commit_all" name="commit_all" type="checkbox" value="1" checked>
                        Commit also new files
                    </label>
                </fieldset>
            </td>
        </tr>
        <tr>
            <th scope="row">Publish</th>
            <td><input id="submit" class="button button-primary" type="submit" name="commit" value="Commit & Push"></td>
        </tr>
<?php
}

function render_push_status($git_unpushed_count, $git_directory) {
?>
        <tr>
            <th scope="row">Status</th>
            <td>
                <p class="description"><?php echo $git_unpushed_count; ?> unpushed commits in <?php echo $git_directory; ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row">Publish</th>
            <td><input id="submit" class="button button-primary" type="submit" name="push" value="Push"></td>
        </tr>
<?php
}

function render_git_status($git_directory) {
    process_post_options($git_directory);

    list($git_status, $git_status_code) = git_status($git_directory);
    list($git_unpushed, $git_unpushed_code) = git_unpushed_commits($git_directory);

    if ($git_status_code == 0 && $git_unpushed_code == 0):
        $git_commit_count = count($git_status);
        $git_unpushed_count = intval($git_unpushed[0]);
        
        if ($git_commit_count == 0 && $git_unpushed_count == 0) {
            render_nothing_to_commit();
            return;
        }
?>
        <form id="general-options" class="options-form" method="post" action="">
        <table class="form-table" role="presentation">
<?php
        if ($git_commit_count > 0) {
            render_commit_status($git_status, $git_unpushed_count, $git_directory);
        } else {
            render_push_status($git_unpushed_count, $git_directory);
        }
?>
        </table>
        </form>
<?php
    else:
        /* error */
        render_error_git_failed($git_directory, $git_status_code);
    endif;
}

function render_error($msg) {
?>    
        <table class="form-table" role="presentation">
        <tr>
            <th scope="row">Status</th>
            <td><i class="fas fa-exclamation-circle"></i> <?php echo $msg; ?></td>
        </tr>
        </table>
<?php
}

function render_error_no_wp2static() {
    render_error("WP2Static plugin not active.");
}

function render_error_wp2static_folder_mode() {
    render_error("WP2Static plugin not in local subdirectory development mode.");
}

function render_error_git_failed($d, $code) {
    render_error("Could not get the status of the Git repository in ".$d." (status code ".intval($code).").");
}

function git_push_page_contents() {
?>    
    <div class="wrap">
    <h1><?php esc_html_e( 'Publish your local Git repository', 'git-push' ); ?></h1>
<?php
    if (is_wp2static_active()) {
        $d = get_wp2static_folder();
        if ($d)
            render_git_status($d);
        else
            render_error_wp2static_folder_mode();
    } else {
        render_error_no_wp2static();
    }
?>
    </div>
<?php
}
?>
