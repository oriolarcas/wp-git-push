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

define("GIT_PATH", "/var/www/web/app/static");

function git_push_admin_init() {
   wp_enqueue_style('fontawesome', 'https://use.fontawesome.com/releases/v5.8.1/css/all.css', '', '5.8.1', 'all');
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

function git_status() {
    exec('cd '.GIT_PATH.' && git status --porcelain', $git_output, $git_code);
    return array($git_output, $git_code);
}

function git_unpushed_commits() {
    exec('cd '.GIT_PATH.' && git rev-list --count origin/master..HEAD', $git_output, $git_code);
    return array($git_output, $git_code);
}

function get_git_status_icon($s) {
    switch ($s) {
    case "M":
        return '<i class="fas fa-edit"></i> ';
    case "D":
        return '<i class="fas fa-trash-alt"></i> ';
    case "A":
        return '<i class="fas fa-plus-square"></i> ';
    case "?":
        return '<i class="fas fa-file"></i> ';
    case "R":
        return '<i class="fas fa-i-cursor"></i> ';
    case " ":
        return '';
    default:
        return '<i class="fas fa-question"></i> ';
    }
}

function git_push_page_contents() {
    list($git_status, $git_status_code) = git_status();
    list($git_unpushed, $git_unpushed_code) = git_unpushed_commits();
?>    
    <div class="wrap">
    <h1><?php esc_html_e( 'Publish your local Git repository', 'git-push' ); ?></h1>
    <table class="form-table" role="presentation">
<?php
    if ($git_status_code == 0 && $git_unpushed_code == 0):
        $git_commit_count = count($git_status);
        $git_unpushed_count = intval($git_unpushed[0]);
?>
        <tr>
            <th scope="row">Status</th>
            <td><div style="max-height:200px; overflow-y: scroll;"><ul class="fa-ul">
<?php
        foreach ($git_status as $f) {
            echo '<li>';
            $index = $f[0];
            $working = $f[1];
            if ($index != " " && $index != "?")
                echo get_git_status_icon($index);
            echo get_git_status_icon($working);
            echo esc_html(substr($f, 3))."</li>\n";
        }
?>
            </ul></div>
            <p class="description"><?php echo $git_commit_count; ?> files with changes, <?php echo $git_unpushed_count; ?> unpushed commits</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Commit message</th>
            <td><textarea class="large-text code" rows="5">Website update</textarea></td>
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
            <td><input id="submit" class="button button-primary" type="submit" name="submit" value="Commit & Push"></td>
        </tr>
<?php
    else:
        /* error */
?>    
        <tr>
            <th scope="row">Status</th>
            <td><i class="fas fa-exclamation-circle"></i> Could not get the status of the Git repository.</td>
        </tr>
<?php
    endif;
?>
    </table>
    </div>
<?php
}
?>
