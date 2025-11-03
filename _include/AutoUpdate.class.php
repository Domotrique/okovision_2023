<?php

/*
namespace VisualAppeal;

use \vierbergenlars\SemVer\version;
use \vierbergenlars\SemVer\expression;
use \vierbergenlars\SemVer\SemVerException;
*/
//use \Desarrolla2\Cache\Cache;
//use \Desarrolla2\Cache\Adapter\NotCache;

/**
 * Auto update class.
 */
class AutoUpdate extends connectDb
{
    /**
     * No update available.
     */
    const NO_UPDATE_AVAILABLE = 0;

    /**
     * Zip file could not be opened.
     */
    const ERROR_INVALID_ZIP = 10;

    /**
     * Could not check for last version.
     */
    const ERROR_VERSION_CHECK = 20;

    /**
     * Could not backup the old install.
     */
    const ERROR_BACKUP = 25;

    /**
     * Temp directory does not exist or is not writable.
     */
    const ERROR_TEMP_DIR = 30;

    /**
     * Install directory does not exist or is not writable.
     */
    const ERROR_INSTALL_DIR = 35;

    /**
     * Could not download update.
     */
    const ERROR_DOWNLOAD_UPDATE = 40;

    /**
     * Could not delete zip update file.
     */
    const ERROR_DELETE_TEMP_UPDATE = 50;

    /**
     * Error while installing the update.
     */
    const ERROR_INSTALL = 60;

    /**
     * Error in simulated install.
     */
    const ERROR_SIMULATE = 70;

    /**
     * Create new folders with this privileges.
     *
     * @var int
     */
    public $dirPermissions = 0755;

    /**
     * Update script filename.
     *
     * @var string
     */
    public $updateScriptName = '_upgrade.php';

    /**
     * Current version.
     *
     * @var vierbergenlars\SemVer\version
     */
    protected $_currentVersion;
    /**
     * The latest version.
     *
     * @var vierbergenlars\SemVer\version
     */
    private $_latestVersion;

    /**
     * Updates not yet installed.
     *
     * @var array
     */
    private $_updates = [];

    /**
     * Cache for update requests.
     *
     * @var Desarrolla2\Cache\Cache
     */
    private $_cache;

    /**
     * Result of simulated install.
     *
     * @var array
     */
    private $_simulationResults = [];

    /**
     * Temporary download directory.
     *
     * @var string
     */
    private $_tempDir = '';

    /**
     * Install directory.
     *
     * @var string
     */
    private $_installDir = '';

    /**
     * Update branch.
     *
     * @var string
     */
    private $_branch = '';

    private bool $zipContact = false;

    /**
     * Create new instance.
     *
     * @param string $tempDir
     * @param string $installDir
     * @param int    $maxExecutionTime
     */
    public function __construct($tempDir = null, $installDir = null, $maxExecutionTime = 120)
    {
        parent::__construct();
        // Init logger
        //$this->log->info('Class '.__CLASS__.' | '.__FUNCTION__);
        //$this->_log->pushHandler(new NullHandler());

        $this->setTempDir('_tmp');
        $this->setInstallDir(CONTEXT);

        $this->_latestVersion = new version('0.0.0');
        $this->_currentVersion = new version('0.0.0');

        // Init cache
        //$this->_cache = new Cache(new NotCache());

        ini_set('max_execution_time', $maxExecutionTime);
    }

    /**
     * Set the temporary download directory.
     *
     * @param string $dir
     */
    public function setTempDir($dir)
    {
        // Add slash at the end of the path
        if (substr($dir, -1) !== '/') 
        { 
            $dir .= '/';
        }

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                $this->log->info(sprintf('Could not create temporary directory "%s"', $dir));

                return;
            }
        }

        $this->_tempDir = $dir;

        return $this;
    }

    /**
     * Set the install directory.
     *
     * @param string $dir
     */
    public function setInstallDir($dir)
    {
        // Add slash at the end of the path
        if (substr($dir, -1) != '/') {
            $dir .= '/';
        }

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                $this->log->info(sprintf('Could not create temporary directory "%s"', $dir));

                return;
            }
        }

        $this->_installDir = $dir;

        return $this;
    }

    /**
     * Set the update filename.
     *
     * @param string $updateFile
     */
    public function setUpdateFile($updateFile)
    {
        $this->_updateFile = $updateFile;

        return $this;
    }

    /**
     * Set the update filename.
     *
     * @param string $updateUrl
     */
    public function setUpdateUrl($updateUrl)
    {
        $this->_updateUrl = $updateUrl;

        return $this;
    }

    /**
     * Set the update branch.
     *
     * @param string branch
     * @param mixed $branch
     */
    public function setBranch($branch)
    {
        $this->_branch = $branch;

        return $this;
    }

    /**
     * Set the cache component.
     *
     * @param Desarrolla2\Cache\Adapter\AdapterInterface $adapter See https://github.com/desarrolla2/Cache
     * @param int                                        $ttl     Time to live in seconds
     */
    public function setCache($adapter, $ttl = 3600)
    {
        $adapter->setOption('ttl', $ttl);
        $this->_cache = new Cache($adapter);

        return $this;
    }

    /**
     * Set the version of the current installed software.
     *
     * @param string $currentVersion
     *
     * @return bool
     */
    public function setCurrentVersion($currentVersion)
    {
        $version = new version($currentVersion);
        if (null === $version->valid()) {
            $this->log->info(sprintf('Invalid current version "%s"', $currentVersion));

            return false;
        }

        $this->_currentVersion = $version;

        return $this;
    }

    /**
     * Add a new logging handler.
     *
     * @param Monolog\Handler\HandlerInterface $handler See https://github.com/Seldaek/monolog
     */
    /*
    public function addLogHandler(\Monolog\Handler\HandlerInterface $handler)
    {
       $this->_log->pushHandler($handler);
       return $this;
    }
*/

    /**
     * Get the name of the latest version.
     *
     * @return vierbergenlars\SemVer\version
     */
    public function getLatestVersion()
    {
        return $this->_latestVersion;
    }

    /**
     * Get an array of versions which will be installed.
     *
     * @return array
     */
    public function getVersionsToUpdate()
    {
        return array_map(function ($update) {
            return $update['version'];
        }, $this->_updates);
    }

    /**
     * Get an array of versions Infos which will be installed.
     *
     * @return array
     */
    public function getVersionsInformationToUpdate()
    {
        $r = [];
        foreach ($this->_updates as $raw => $info) {
            $r[] = ['version' => $info['version']->getVersion(),
                'url' => $info['url'],
                'changelog' => $info['changelog'],
                'date' => $info['date'],
            ];
        }
        //return json_encode( array_reverse($r) );
        return array_reverse($r);
    }

    /**
     * Get the results of the last simulation.
     *
     * @return array
     */
    public function getSimulationResults()
    {
        return $this->_simulationResults;
    }

    /**
     * Check for a new version.
     *
     * @return bool|int
     *                  true: New version is available
     *                  false: Error while checking for update
     *                  int: Status code (i.e. AutoUpdate::NO_UPDATE_AVAILABLE)
     */
    public function checkUpdate()
    {   
        $result = [
            'status'  => 'ok',          // ok / error / no_update / update_available
            'message' => '',
        ];
        
        $this->log->debug('Checking for a new update...');

        // Reset previous updates
        $this->_latestVersion = new version('0.0.0');
        $this->_updates = [];
		
		if (empty(REPO_VERSION_API)) {
            $result['status']  = 'error';
            $result['message'] = 'URL de mise à jour manquante';
            $this->log->error($result['message']);
            return $result;
        }

        $ch = curl_init(REPO_VERSION_API);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT      => 'OkovisionDownloader',
            CURLOPT_HTTPHEADER     => ['Accept: application/vnd.github+json'],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        ]);

        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        $error    = curl_error($ch);
        $info     = curl_getinfo($ch);
        curl_close($ch);

        if ($response === false) {
            $msg = sprintf("Erreur cURL (%d): %s", $errno, $error);
            $this->log->error($msg . ' | url=' . REPO_VERSION_API . ' | http_code=' . ($info['http_code'] ?? 'N/A'));
            $result['status']  = 'error';
            $result['message'] = "Impossible de contacter le serveur de mise à jour : $error";
            return $result;
        }

        $data = json_decode($response, true);
        if ($data === null) {
            $result['status']  = 'error';
            $result['message'] = "Réponse du serveur de mise à jour invalide (non JSON).";
            $this->log->error($result['message']);
            return $result;
        }

        if (!isset($data['tag_name'])) {
            $result['status']  = 'no_update';
            $result['message'] = "Aucune release trouvée.";
            return $result;
        }

        $version = new version($data['tag_name']);
        $this->log->info(sprintf('Dernière version d\'Okovision 2023 : ' . $data['tag_name']));
        
        if (version::gt($version, $this->_currentVersion)) {
            $result['status']  = 'update_available';
            $result['message'] = "Nouvelle version disponible : " . $data['tag_name'];
            $this->_latestVersion = $version;
            $this->log->info(sprintf('New version "%s" available', $this->_latestVersion));
            $this->_updates[] = [
                'version' => $version,
                'url' => $data['zipball_url'] ?? $data['tarball_url'] ?? null,
                // Format changelog proprement en remplaçant les titres Markdown par des balises HTML
                'changelog' => isset($data['body']) 
                    ? nl2br(
                        preg_replace([
                            '/^### (.*)$/m', // Titres niveau 3
                            '/^## (.*)$/m',  // Titres niveau 2
                            '/^# (.*)$/m',   // Titres niveau 1
                            '/\*\*(.*?)\*\*/', // Gras Markdown
                            '/\*(.*?)\*/',     // Italique Markdown
                        ], [
                            '<h3>$1</h3>',
                            '<h2>$1</h2>',
                            '<h1>$1</h1>',
                            '<strong>$1</strong>',
                            '<em>$1</em>',
                        ], $data['body'])
                    )
                    : null,
                'date' => isset($data['published_at']) ? substr($data['published_at'], 0, 10) : null,
            ];
            return $result;
        }

        $result['status']  = 'no_update';
        $result['message'] = "Aucune mise à jour disponible.";
        return $result;
    }

    /**
     * Check if a new version is available.
     *
     * @return bool
     */
    public function newVersionAvailable()
    {
        return version::gt($this->_latestVersion, $this->_currentVersion);
    }

    /**
     * Update to the latest version.
     *
     * @param bool $simulateInstall Check for directory and file permissions before copying files (Default: true)
     * @param bool $deleteDownload  Delete download after update (Default: true)
     *
     * @return mixed integer|bool
     */
    public function update($simulateInstall = true, $deleteDownload = true)
    {
        $this->log->info('Trying to perform update');

        // Check for latest version
        if (null === $this->_latestVersion || 0 === count($this->_updates)) {
            $this->checkUpdate();
        }

        if (null === $this->_latestVersion || 0 === count($this->_updates)) {
            $this->log->error('Could not get latest version from server!');

            return self::ERROR_VERSION_CHECK;
        }

        // Check if current version is up to date
        if (!$this->newVersionAvailable()) {
            $this->log->warn('No update available!');

            return self::NO_UPDATE_AVAILABLE;
        }

        foreach ($this->_updates as $update) {
            $this->log->debug(sprintf('Update to version "%s"', $update['version']));

            // Check for temp directory
            if (empty($this->_tempDir) || !is_dir($this->_tempDir) || !is_writable($this->_tempDir)) {
                $this->log->fatal(sprintf('Temporary directory "%s" does not exist or is not writeable!', $this->_tempDir));

                return self::ERROR_TEMP_DIR;
            }

            // Check for install directory
            if (empty($this->_installDir) || !is_dir($this->_installDir) || !is_writable($this->_installDir)) {
                $this->log->fatal(sprintf('Install directory "%s" does not exist or is not writeable!', $this->_installDir));

                return self::ERROR_INSTALL_DIR;
            }

            $updateFile = $this->_tempDir.$update['version'].'.zip';

            // Download update
            if (!is_file($updateFile)) {
                if (!$this->_downloadUpdate($update['url'], $updateFile)) {
                    $this->log->fatal(sprintf('Failed to download update from "%s" to "%s"!', $update['url'], $updateFile));

                    return self::ERROR_DOWNLOAD_UPDATE;
                }

                $this->log->debug(sprintf('Latest update downloaded to "%s"', $updateFile));
            } else {
                $this->log->info(sprintf('Latest update already downloaded to "%s"', $updateFile));
            }

            // Install update
            
            //$this->_createBackup(); //FOR DEBUG
            $result = $this->_install($updateFile, $simulateInstall, $update['version']);
            if (true === $result) {
                $this->updateConfigVersion($this->_latestVersion->getVersion());
                if ($deleteDownload) {
                    $this->log->debug(sprintf('Trying to delete update file "%s" after successfull update', $updateFile));
                    if (@unlink($updateFile)) {
                        $this->log->info(sprintf('Update file "%s" deleted after successfull update', $updateFile));
                    } else {
                        $this->log->error(sprintf('Could not delete update file "%s" after successfull update!', $updateFile));

                        return self::ERROR_DELETE_TEMP_UPDATE;
                    }
                }
            } else {
                if ($deleteDownload) {
                    $this->log->debug(sprintf('Trying to delete update file "%s" after failed update', $updateFile));
                    if (@unlink($updateFile)) {
                        $this->log->info(sprintf('Update file "%s" deleted after failed update', $updateFile));
                    } else {
                        $this->log->error(sprintf('Could not delete update file "%s" after failed update!', $updateFile));
                    }
                }

                return $result;
            }
        }

        return true;
    }

    /**
     * Download the update.
     *
     * @param string $updateUrl  Url where to download from
     * @param string $updateFile Path where to save the download
     *
     * @return bool
     */
    protected function _downloadUpdate($updateUrl, $updateFile)
    {
        $this->log->info(sprintf('Downloading update "%s" to "%s"', $updateUrl, $updateFile));

        $ch = curl_init($updateUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'OkovisionDownloader'); // GitHub requires a User-Agent
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $update = curl_exec($ch);

        if ($update === false) {
            $this->log->error(sprintf('Could not download update "%s"! Curl error: %s', $updateUrl, curl_error($ch)));
            curl_close($ch);
            return false;
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $this->log->error(sprintf('Download failed with HTTP code %d for "%s"', $httpCode, $updateUrl));
            return false;
        }

        $handle = fopen($updateFile, 'w');
        if (!$handle) {
            $this->log->error(sprintf('Could not open file handle to save update to "%s"!', $updateFile));
            return false;
        }

        if (fwrite($handle, $update) === false) {
            $this->log->error(sprintf('Could not write update to file "%s"!', $updateFile));
            fclose($handle);
            return false;
        }

        fclose($handle);

        return true;
    }

    /**
     * Simulate update process.
     *
     * @param string $updateFile
     *
     * @return bool
     */
    protected function _simulateInstall($updateFile)
    {
        $this->log->info('[SIMULATE] Install new version');
        clearstatcache();

        $zip = new ZipArchive();
        if ($zip->open($updateFile) !== true) {
            $this->log->error(sprintf('Could not open zip file "%s" with ZipArchive', $updateFile));
            return false;
        }

        $files = [];
        $simulateSuccess = true;

        // Détecte le dossier racine GitHub (préfixe commun) : p.ex. okovision_2023-<hash>/
        $prefix = '';
        if ($zip->numFiles > 0) {
            $first = $zip->statIndex(0);
            if ($first && isset($first['name'])) {
                $parts = explode('/', $first['name']);
                $prefix = rtrim($parts[0] ?? '', '/') . '/';
            }
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat || !isset($stat['name'])) {
                continue;
            }

            // Nom interne dans l’archive (avec ou sans préfixe)
            $internalName = $stat['name'];

            // Ignore la racine
            if ($i === 0 && substr($internalName, -1) === '/') {
                continue;
            }

            // Chemin relatif à installer (on enlève le préfixe GitHub si présent)
            $filename = $prefix && str_starts_with($internalName, $prefix)
                ? substr($internalName, strlen($prefix))
                : $internalName;

            $foldername = $this->_installDir . dirname($filename);
            $absoluteFilename = $this->_installDir . $filename;

            $files[$i] = [
                'filename'           => $filename,
                'foldername'         => $foldername,
                'absolute_filename'  => $absoluteFilename,
            ];

            $this->log->debug(sprintf('[SIMULATE] Updating file "%s"', $filename));

            // Dossier parent
            if (!is_dir($foldername)) {
                $this->log->debug(sprintf('[SIMULATE] Create directory "%s"', $foldername));
                $files[$i]['parent_folder_exists'] = false;

                $parent = dirname($foldername);
                if (!is_writable($parent) && is_dir($parent)) {
                    $files[$i]['parent_folder_writable'] = false;
                    $simulateSuccess = false;
                    $this->log->warn(sprintf('[SIMULATE] Directory "%s" has to be writeable!', $parent));
                } else {
                    $files[$i]['parent_folder_writable'] = true;
                }
            }

            // Répertoires dans le zip : on skip
            if (substr($internalName, -1) === '/') {
                continue;
            }

            // Lecture du contenu (équiv. zip_entry_read)
            $contents = $zip->getFromIndex($i);
            if ($contents === false) {
                $files[$i]['extractable'] = false;
                $simulateSuccess = false;
                $this->log->warn(sprintf('[SIMULATE] Could not read contents of "%s" from zip!', $filename));
            }

            // Vérif d’écriture
            if (file_exists($absoluteFilename)) {
                $files[$i]['file_exists'] = true;
                if (!is_writable($absoluteFilename)) {
                    $files[$i]['file_writable'] = false;
                    $simulateSuccess = false;
                    $this->log->warn(sprintf('[SIMULATE] Could not overwrite "%s"!', $absoluteFilename));
                }
            } else {
                $files[$i]['file_exists'] = false;
                if (is_dir($foldername)) {
                    if (!is_writable($foldername)) {
                        $files[$i]['file_writable'] = false;
                        $simulateSuccess = false;
                        $this->log->warn(sprintf('[SIMULATE] The file "%s" could not be created!', $absoluteFilename));
                    } else {
                        $files[$i]['file_writable'] = true;
                    }
                } else {
                    $files[$i]['file_writable'] = true;
                    $this->log->debug(sprintf('[SIMULATE] The file "%s" could be created', $absoluteFilename));
                }
            }

            $files[$i]['update_script'] = ($filename === $this->updateScriptName);
        }

        $this->_simulationResults = $files;
        $zip->close();

        return $simulateSuccess;
    }

    /**
     * Install update.
     *
     * @param string $updateFile      Path to the update file
     * @param bool   $simulateInstall Check for directory and file permissions before copying files
     * @param mixed  $version
     *
     * @return bool
     */
    protected function _install($updateFile, $simulateInstall, $version)
    {
        $this->log->info(sprintf('Trying to install update "%s" (ZipArchive)', $updateFile));

        if ($simulateInstall && !$this->_simulateInstall($updateFile)) {
            $this->log->fatal('Simulation of update process failed!');
            return self::ERROR_SIMULATE;
        }

        clearstatcache();

        $zip = new ZipArchive();
        if ($zip->open($updateFile) !== true) {
            $this->log->error(sprintf('Could not open zip file "%s" with ZipArchive', $updateFile));
            return self::ERROR_INVALID_ZIP;
        }

        // Détecte le préfixe racine GitHub (voir ci-dessus)
        $prefix = '';
        if ($zip->numFiles > 0) {
            $first = $zip->statIndex(0);
            if ($first && isset($first['name'])) {
                $parts = explode('/', $first['name']);
                $prefix = rtrim($parts[0] ?? '', '/') . '/';
            }
        }

        $updateScriptExist = false;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat || !isset($stat['name'])) {
                continue;
            }

            $internalName = $stat['name'];

            // Ignore le répertoire racine
            if ($i === 0 && substr($internalName, -1) === '/') {
                continue;
            }

            $filename = $prefix && str_starts_with($internalName, $prefix)
                ? substr($internalName, strlen($prefix))
                : $internalName;

            $foldername = $this->_installDir . dirname($filename);
            $absoluteFilename = $this->_installDir . $filename;

            $this->log->debug(sprintf('Updating file "%s"', $filename));

            if (!is_dir($foldername)) {
                if (!mkdir($foldername, $this->dirPermissions, true)) {
                    $this->log->error(sprintf('Directory "%s" has to be writeable!', $foldername));
                    $zip->close();
                    return false;
                }
            }

            // Répertoires => skip
            if (substr($internalName, -1) === '/') {
                continue;
            }

            $contents = $zip->getFromIndex($i);
            if ($contents === false) {
                $this->log->error(sprintf('Could not read zip entry "%s"', $internalName));
                continue;
            }

            if (file_exists($absoluteFilename)) {
                if (!is_writable($absoluteFilename)) {
                    $this->log->error(sprintf('Could not overwrite "%s"!', $absoluteFilename));
                    $zip->close();
                    return false;
                }
            } else {
                if (!touch($absoluteFilename)) {
                    $this->log->error(sprintf('The file "%s" could not be created!', $absoluteFilename));
                    $zip->close();
                    return false;
                }
                $this->log->debug(sprintf('File "%s" created', $absoluteFilename));
            }

            $updateHandle = @fopen($absoluteFilename, 'w');
            if (!$updateHandle) {
                $this->log->error(sprintf('Could not open file "%s"!', $absoluteFilename));
                $zip->close();
                return false;
            }

            if (fwrite($updateHandle, $contents) === false) {
                fclose($updateHandle);
                $this->log->error(sprintf('Could not write to file "%s"!', $absoluteFilename));
                $zip->close();
                return false;
            }
            fclose($updateHandle);

            if ($filename === $this->updateScriptName) {
                $updateScriptExist = true;
            }
        }

        $zip->close();

        // Exécute le script d’upgrade à la fin
        if ($updateScriptExist) {
            $upgradeFile = $this->_installDir . $this->updateScriptName;
            $this->log->debug(sprintf('Try to include update script "%s"', $upgradeFile));

            require $upgradeFile;

            $this->log->info(sprintf('Update script "%s" included!', $upgradeFile));

            if (!DEBUG) {
                if (!@unlink($upgradeFile)) {
                    $this->log->warn(sprintf('Could not delete update script "%s"!', $upgradeFile));
                }
            }
        }

        $this->log->info(sprintf('Update "%s" successfully installed', $version));
        return true;
    }

    /**
     * Remove directory recursively.
     *
     * @param string $dir
     */
    private function _removeDir($dir)
    {
        $this->log->debug(sprintf('Remove directory "%s"', $dir));

        if (!is_dir($dir)) {
            $this->log->warn(sprintf('"%s" is not a directory!', $dir));

            return false;
        }

        $objects = array_diff(scandir($dir), ['.', '..']);
        foreach ($objects as $object) {
            if (is_dir($dir.'/'.$object)) {
                $this->_removeDir($dir.'/'.$object);
            } else {
                unlink($dir.'/'.$object);
            }
        }

        return rmdir($dir);
    }

    private function _createBackup()
    {
        // Get real path for our folder
        $dir = str_replace('\\', '/', getcwd())."/";
        $zip_file = "_BACKUP.zip";

        if (file_exists($zip_file)) {
            rename($zip_file, "../".date("Ymd").$zip_file);
        }

        $zip = new ZipArchive;
        if ($zip -> open($zip_file, ZipArchive::CREATE) === TRUE)
        {
            $this->addFolderToZip($dir,$zip);

            $zip->close();

            if (file_exists($zip_file)) {
                return true;
            } else {
                return false;
            }
        }

    }

    // Function to recursively add a directory,
    // sub-directories and files to a zip archive
    function addFolderToZip($dir, $zipArchive){
        if( basename($dir) != "okovision_2023") {
            $zipContact = true;
        }
        if (is_dir($dir)) {
            if ($dh = opendir($dir)) {
                /*if (str_contains($dir, "okovision_2023")) {
                    $zipArchive->addEmptyDir("okovision_2023");
                } else {*/
                    //Add the directory
                    if (!$zipContact) {
                        $zipArchive->addEmptyDir(basename($dir));
                    } else {
                        $zipArchive->addEmptyDir($dir);
                    }
               // }

                // Loop through all the files
                while (($file = readdir($dh)) !== false) {
                    //If it's a folder, run the function again!
                    if (!is_file($dir . $file)) {
                        // Skip parent and root directories
                        if( ($file !== ".") && ($file !== "..")){
                            $this->addFolderToZip($dir . $file . "/", $zipArchive);
                        }
                    } else {
                        // Add the files
                        $zipArchive->addFile($dir . $file);

                    }
                }
            }
        }
        return 0;
    }

    /**
     * Met à jour la constante OKOVISION_VERSION dans config.php.
     *
     * @param string $newVersion
     *
     * @return bool
     */
    private function updateConfigVersion($newVersion)
    {
        $configPath = CONTEXT . '/config.php';
        if (!file_exists($configPath)) {
            $this->log->error("config.php introuvable !");
            return false;
        }

        $content = file_get_contents($configPath);

        // Remplace la ligne de version existante par la nouvelle
        $content = preg_replace(
            "/DEFINE\('OKOVISION_VERSION','[^']*'\);/",
            "DEFINE('OKOVISION_VERSION','" . $newVersion . "');",
            $content
        );

        file_put_contents($configPath, $content);
        $this->log->info("OKOVISION_VERSION mis à jour dans config.php : " . $newVersion);
        return true;
    }
}
