<?php
/**
 * This command will get all server logs on all appservers of a specific environment
 * specially on plans that has multiple appservers on live and test.
 *
 * Big thanks to Greg Anderson. Some of the codes are from his rsync plugin
 * https://github.com/pantheon-systems/terminus-rsync-plugin
 */

namespace Pantheon\TerminusLogsStreamer\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\Terminus\Commands\StructuredListTrait;
use Pantheon\Terminus\Exceptions\TerminusException;
use Pantheon\Terminus\Site\SiteAwareInterface;
use Pantheon\Terminus\Site\SiteAwareTrait;
use Symfony\Component\Filesystem\Filesystem;
use Pantheon\Terminus\Commands\Remote\DrushCommand;

use Pantheon\TerminusLogsStreamer\Utility\Commons;

/**
 * Class SiteLogsCommand
 * @package Pantheon\TerminusLogsStreamer\Commands
 */
class SiteLogsCommand extends TerminusCommand implements SiteAwareInterface
{
    use SiteAwareTrait;
    use StructuredListTrait;

    /**
     * @var
     */
    private $site;

    /**
     * @var
     */
    private $environment;

    /**
     * @var false|string
     */
    private $width;

    /**
     * @var string
     */
    private $logPath;

    /**
     * @var string
     */
    private $localLogFile;

    /**
     * Object constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->width = exec("echo $(/usr/bin/tput cols)");
        // Define the log path.
        $this->logPath = getenv('HOME') . '/.terminus/site-logs/';
    }

    /**
     * @var string[]
     */
    private $logs_filename = [
        'nginx-access',
        'nginx-error',
        'php-error',
        'php-fpm-error',
        'php-slow',
    ];

    private $options = [
        'exclude' => true, 
        'all' => false, 
        'nginx-access' => false, 
        'nginx-error' => false, 
        'php-fpm-error' => false, 
        'php-slow' => false, 
        'pyinotify' => false, 
        'watcher' => false, 
        'newrelic' => true,
    ];

    /**
     * Stream the logs.
     *
     * @command logs:stream
     * @aliases stream
     * 
     * @usage <site>.<env> 
     * 
     * To stream Nginx access log.
     *   terminus logs:stream <site>.<env> -- --nginx-access
     * 
     * To stream PHP slow log.
     *   terminus logs:stream <site>.<env> -- --php-slow
     * 
     * To stream PHP error log.
     *   terminus logs:stream <site>.<env> -- --php-error
     */
    public function LogsStream($options, $site_env, $dest = null) {
        
        // Create the logs directory if not present.
        if (!is_dir($this->logPath))
        {
            //$this->log()->error('Logs directory not found.');
            // Create the logs directory if not present.
            //$this->log()->notice('Creating logs directory.');
            mkdir($this->logPath, 0777, true);
        }
         
        // Get env_id and site_id.
        $this->DefineSiteEnv($site_env);
        $site = $this->site->get('name');
        $env = $this->environment->id;
        $env_id = $this->environment->get('id');
        $site_id = $this->site->get('id');

        // Set src and files.
        $src = "$env_id.$site_id";
        $files = '*.log';

        print_r($this);

        /*
        // If the destination parameter is empty, set destination to ~/.terminus/site-logs/[sitename]/[env]/.
        if (!$dest) 
        {
            $dest = $this->logPath . '/'. $site . '/' . $env;
        }

        // Lists of files to be excluded.
        $rsync_options = $this->RsyncOptions($options);

        // Get all appservers' IP address.
        $appserver_dns_records = dns_get_record("appserver.$env_id.$site_id.drush.in", DNS_A);
        // Get dbserver IP address.
        $dbserver_dns_records = dns_get_record("dbserver.$env_id.$site_id.drush.in", DNS_A);

        $this->log()->notice('Downloading logs from appserver...');
        // Appserver - Loop through the record and download the logs.
        foreach($appserver_dns_records as $appserver) 
        {
            $app_server_ip = $appserver['ip'];
            $dir = $dest . '/' . $app_server_ip;

            if (!is_dir($dir)) 
            {
                mkdir($dir, 0777, true);
            }

            if ($options['all'])
            {
                $this->log()->notice('Running {cmd}', ['cmd' => "rsync $rsync_options $src@$app_server_ip:logs/* $dir"]);
                $this->passthru("rsync $rsync_options -zi --progress --ipv4 --exclude=.git -e 'ssh -p 2222' $src@$app_server_ip:logs/* $dir >/dev/null 2>&1");
            }
            else
            {
                $this->log()->notice('Running {cmd}', ['cmd' => "rsync $rsync_options $src@$app_server_ip:logs/ $dir"]);
                $this->passthru("rsync $rsync_options -zi --progress --ipv4 --exclude=.git -e 'ssh -p 2222' $src@$app_server_ip:logs/nginx/* $dir >/dev/null 2>&1");
                $this->passthru("rsync $rsync_options -zi --progress --ipv4 --exclude=.git -e 'ssh -p 2222' $src@$app_server_ip:logs/php/* $dir >/dev/null 2>&1");
            }
        }

        // DBserver - Loop through the record and download the logs.
        foreach($dbserver_dns_records as $dbserver) 
        {
            $db_server_ip = $dbserver['ip'];
            $dir = $dest . '/' . $db_server_ip;

            if (!is_dir($dir)) 
            {
                mkdir($dir, 0777, true);
            }

            $this->log()->notice('Downloading logs from dbserver...');
            $this->log()->notice('Running {cmd}', ['cmd' => "rsync $rsync_options $src@$db_server_ip:logs/*.log $dir"]);
            $this->passthru("rsync $rsync_options -zi --progress --ipv4 --exclude=.git -e 'ssh -p 2222' $src@$db_server_ip:logs/*.log $dir >/dev/null 2>&1");
        }
        */
    }

    /**
     * Passthru command. 
     */
    protected function passthru($command)
    {
        $result = 0;
        passthru($command, $result);

        if ($result != 0) 
        {
            throw new TerminusException('Command `{command}` failed with exit code {status}', ['command' => $command, 'status' => $result]);
        }
    }

    // Function to check for new log entries and output them
    private function tailLogFile($filePointer, &$lastReadPosition) {
        clearstatcache(true, $GLOBALS['localLogFile']);
        $newFileSize = filesize($GLOBALS['localLogFile']);
    
        // Check if the file size has increased (indicating new content)
        if ($newFileSize > $lastReadPosition) {
            fseek($filePointer, $lastReadPosition);
            $newEntries = fread($filePointer, $newFileSize - $lastReadPosition);
            $lastReadPosition = $newFileSize;
    
            // Output the new entries
            echo $newEntries;
        }
    }

    /**
     * Rsync options.
     */
    private function RsyncOptions($options) 
    {
      $rsync_options = '';
      $exclude = $this->ParseExclude($options);

      foreach($exclude as $item) 
      {
        $rsync_options .= "--exclude $item ";
      }

      return $rsync_options;
    }

    /**
     * Exclude files and dirs.
     */
    private function Exclude()
    {
        return ['.DS_Store', '.', '..'];
    }
}
