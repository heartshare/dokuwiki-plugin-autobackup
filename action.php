<?php
/**
 * DokuWiki Plugin autobackup (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Robert McLeod <hamstar@telescum.co.nz>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

// Custom constants
if (!defined('DOKU_DATA')) define('DOKU_DATA', "/var/lib/dokuwiki/data/");
if (!defined('AUTOBACKUP_PLUGIN')) define('AUTOBACKUP_PLUGIN', DOKU_PLUGIN.'autobackup/');

require_once DOKU_PLUGIN.'action.php';

class action_plugin_autobackup extends DokuWiki_Action_Plugin {

    private $dropbox_enabled_users;
    private $dropbox_enable_queue;
    private $dropbox_disable_queue;
    private $restore_queue;
    private $user;

    public function __construct() {

      $this->dropbox_enabled_users = DOKU_DATA.'pages/braincase/dropbox/enabled_users.txt';
      $this->dropbox_enable_queue = DOKU_DATA.'pages/braincase/dropbox/enable_queue.txt';
      $this->dropbox_disable_queue = DOKU_DATA.'pages/braincase/dropbox/disable_queue.txt';
      $this->restore_queue = DOKU_DATA."pages/braincase/backup/restore_queue.txt";

      foreach ( array(
          $this->dropbox_enabled_users,
          $this->dropbox_enable_queue,
          $this->dropbox_disable_queue,
          $this->restore_queue
        ) as $file )
        if ( !file_exists( $file ) )
          touch( $file );
    }

    /**
     * Register hooks from dokuwiki
     */
    public function register(Doku_Event_Handler &$controller) {

       $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handle_action_act_preprocess');
       $controller->register_hook('TPL_CONTENT_DISPLAY', 'BEFORE', $this, 'handle_tpl_content_display');
       $controller->register_hook('TPL_ACT_UNKNOWN', 'BEFORE', $this, 'handle_tpl_act_unknown');
       $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handle_ajax_call_unknown');   
    }

    public function handle_action_act_preprocess(Doku_Event &$event, $param) {

      switch ( $event->data ) {
          case "memories":
            if ( $this->user ) {
              send_redirect("/doku.php?do=login");
              return;
            }
            $event->preventDefault();
            break;
          default:
            return;
            break;
        }
    }

    public function handle_ajax_call_unknown(Doku_Event &$event, $param) {
      
      $this->set_user();

      $event->preventDefault();
      $event->stopPropagation();

      switch ( $event->data ) {
        case "dropbox.enable":
          $message = Dropbox::enable_for( $this->user );
          break;
        case "dropbox.disable":
          $message = Dropbox::disable_for( $this->user );
          break;
        default:
          $message = "Unsupported request";
          break;
      }

      echo json_encode(array("message" => $message));
    }

    public function handle_tpl_content_display(Doku_Event &$event, $param) {
    }

    public function handle_tpl_act_unknown(Doku_Event &$event, $param) {

      $this->set_user();
      
      try {
        switch ( $event->data ) {
          case "memories":
            echo "<h2>Memories</h2>";
            $this->_show_backup_options();
            $this->_show_memories();
            $event->preventDefault();
            break;
          default:
            return;
            break;
        }
      } catch ( Exception $e ) {
        echo $e->getMessage();
      }
    }

    function set_user() {

      if ( !is_array( $_SESSION ) ) {
        $this->user = "unknown";
        return false;
      }

      $session = reset($_SESSION);
      $this->user = $session["auth"]["user"];
    }

    /**
     * Prints out the backup options
     */
    private function _show_backup_options() {

      $dropbox_status = Dropbox::status_for( $this->user );
      $dropbox_button = Dropbox::generate_button( $this->user );

      include AUTOBACKUP_PLUGIN."inc/backup_options.php"; # TODO: not this
    }

    private function _show_memories() {

      $memory_list = "/home/{$this->user}/memories.list";

      $backups = array();
      
      if ( file_exists($memory_list) )
        $backups = json_decode( file_get_contents( $memory_list ) );

      $current = new StdClass;
      $current->date = "Current";
      $current->source = "Dokuwiki";

      array_unshift( $backups, $current );

      include AUTOBACKUP_PLUGIN."inc/memories.php"; # TODO: not this
    }
}

// vim:ts=4:sw=4:et: