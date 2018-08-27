<?php
/**
 * Created by PhpStorm.
 * User: lapotchkin
 * Date: 02.11.2017
 * Time: 20:49
 */

namespace classes;

use GetOpt\GetOpt;
use GetOpt\Option;
use GuzzleHttp\Client;
use PHPHtmlParser\Dom;

/**
 * Class Grabber
 * @package classes
 */
class Grabber {
    private $_sIssue = NULL;
    private $_sDomain = 'http://www.jurnalu.ru';
    private $_sPathToSave = 'issues';
    private $_sDirToSavePath = NULL;
    private $_nFileNameIterator = 1;
    private $_sTitle = NULL;
    private $_fGrabSeries = FALSE;
    private $_sNextIssueUrl = NULL;
    private $_nDownloadedIssues = 0;
    private $_nIssuesNumber = NULL;
    private $_sSite = 'jurnalu';

    public function __construct() {

    }

    private static function logger($sMessage) {
        echo date('H:i:s') . "\t{$sMessage}" . PHP_EOL;
    }

    private static function _png2jpg($originalFile, $outputFile, $quality) {
        $image = imagecreatefrompng($originalFile);
        imagejpeg($image, $outputFile, $quality);
        imagedestroy($image);
    }

    private static function _sanitizeFileName($dangerous_filename) {
        return str_replace('# ', '', str_replace(':', ' - ', $dangerous_filename));
    }

    private static function _deleteDir($dirPath) {
        if (!is_dir($dirPath)) {
            throw new \InvalidArgumentException("$dirPath must be a directory");
        }
        if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
            $dirPath .= '/';
        }
        $files = glob($dirPath . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_dir($file)) {
                self::_deleteDir($file);
            } else {
                unlink($file);
            }
        }
        rmdir($dirPath);
    }

    private function _saveImage($sUrl) {
        $oDom = new Dom();
        $oDom->load($sUrl);

        if ($this->_nFileNameIterator === 1) {
            $oTitle = $oDom->find('title', 0);
            $aTitle = explode('/', $oTitle->text);
            $this->_sTitle = self::_sanitizeFileName(trim($aTitle[0]), 'Unix');
        }

        switch ($this->_sSite) {
            case 'бомж':
                $oNextPicUrl = $oDom->find('a');
                $sNextPicUrl =
                    (
                        $oNextPicUrl[0]->getAttributes()['href'] === $oNextPicUrl[1]->getAttributes()['href']
                        && $this->_nFileNameIterator !== 1
                    )
                    || $oNextPicUrl[1]->innerHtml() === ''
                    ? NULL
                    : str_replace(basename($sUrl), '', $sUrl) . $oNextPicUrl[1]->getAttributes()['href'];
                $oPage = $oDom->find('img');
                $sPageUrl = str_replace(basename($sUrl), '', $sUrl) . $oPage[0]->getAttributes()['src'];
                break;
            default:
                $oNextPicUrl = $oDom->find('div.ForRead a');
                $sNextPicUrl = $oNextPicUrl[1]->getAttributes()['href'];
                $oPage = $oNextPicUrl[1]->find('img');
                $sPageUrl = $oPage[0]->getAttributes()['src'];
        }

        $headers = ['Referer' => $sUrl];
        $oGuzzleClient = new Client();
        $sPageNumber = str_pad($this->_nFileNameIterator, 4, '0', STR_PAD_LEFT);
        $sFileName = $this->_sDirToSavePath . '/' . $sPageNumber . '.jpg';
        $this->_nFileNameIterator += 1;
        $oGuzzleClient->get(
            $sPageUrl,
            [
                'headers' => $headers,
                'sink'    => $sFileName,
            ]
        );

        $aImageData = getimagesize($sFileName);
        if ($aImageData['mime'] !== 'image/jpeg') {
            if ($aImageData['mime'] === 'image/png') {
                self::_png2jpg($sFileName, $this->_sDirToSavePath . '/' . $sPageNumber . '.jpg', 70);
            } else {
                self::logger("Image is not JPEG but {$aImageData['mime']}");
                exit(400);
            }
        }

        self::logger(
            "Page: " . (basename($sUrl) === $this->_sIssue ? 1 : (int)basename($sUrl)) . ", " . 'URL = ' . $sPageUrl
        );

        switch ($this->_sSite) {
            case 'бомж':
                if (!is_null($sNextPicUrl)) {
                    $this->_saveImage($sNextPicUrl);
                }
                break;
            default:
                if (strstr($sNextPicUrl, $this->_sIssue)) {
                    $this->_saveImage($this->_sDomain . $sNextPicUrl);
                } else {
                    $this->_sNextIssueUrl = !$sNextPicUrl ? NULL : $this->_sDomain . $sNextPicUrl;
                }
        }
    }

    private function _zipIssue($sUrl) {
        $this->_nFileNameIterator = 1;
        $this->_sIssue = basename($sUrl);
        self::logger('Grab issue: ' . $this->_sIssue);

        $this->_sDirToSavePath = $this->_sPathToSave . '/' . $this->_sIssue;
        if (file_exists($this->_sDirToSavePath)) {
            self::_deleteDir($this->_sDirToSavePath);
        }
        mkdir($this->_sDirToSavePath);
        $this->_saveImage($sUrl);

        self::logger('Issue ' . $this->_sIssue . ' downloaded');

        $oZip = new \ZipArchive();
        $oZip->open(
            $this->_sPathToSave . '/' . $this->_sTitle . '.cbz',
            \ZipArchive::CREATE | \ZipArchive::OVERWRITE
        );

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->_sDirToSavePath),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            // Skip directories (they would be added automatically)
            if (!$file->isDir()) {
                // Get real and relative path for current file
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($this->_sDirToSavePath) + 1);

                // Add current file to archive
                $oZip->addFile($filePath, $relativePath);
            }
        }

        $oZip->close();

        self::logger('Issue ' . $this->_sIssue . ' zipped');

        self::_deleteDir($this->_sDirToSavePath);

        $this->_nDownloadedIssues += 1;

        if (!is_null($this->_sNextIssueUrl)) {
            if (is_null($this->_nIssuesNumber) || $this->_nDownloadedIssues !== $this->_nIssuesNumber) {
                $this->_zipIssue($this->_sNextIssueUrl);
            }
        }
    }

    public function actionGrabIssue() {
        try {
            $oGetOpt = new GetOpt();

            $oOptionUrl = new Option('u', 'url', GetOpt::REQUIRED_ARGUMENT);
            $oOptionUrl->setDescription('Issue URL at Jurnalu.RU');
            $oOptionUrl->setArgumentName('URL');

            $oOptionNumber = new Option('i', 'issues', GetOpt::REQUIRED_ARGUMENT);
            $oOptionNumber->setDescription('Number of issues to download');
            $oOptionNumber->setArgumentName('Number of issues');

            $oOptionSeries = new Option('s', 'series', GetOpt::NO_ARGUMENT);
            $oOptionSeries->setDescription('Download all issues of a series');

            $oGetOpt->addOptions([$oOptionUrl, $oOptionNumber, $oOptionSeries]);
            $oGetOpt->process();

            $this->_fGrabSeries = $oGetOpt->getOption('s') ? TRUE : FALSE;
            if ($this->_fGrabSeries) {
                $this->_nIssuesNumber = $oGetOpt->getOption('i') ? intval($oGetOpt->getOption('i')) : NULL;
            }
            $sUrl = $oGetOpt->getOption('u');

            if (is_null($sUrl)) {
                throw new \Exception("Option 'url' must have a value");
            }

            if (strstr($sUrl, 'ruscomarchiv')) {
                $this->_sSite = 'бомж';
            }

            $this->_zipIssue($sUrl);
        } catch (\Exception $e) {
            self::logger($e->getMessage());
        }
    }
}
