<?php
/**
 * ownCloud - Ownpad
 *
 * This file is licensed under the Affero General Public License
 * version 3 or later. See the COPYING file.
 *
 * @author Olivier Tétard <olivier.tetard@miskin.fr>
 * @copyright Olivier Tétard <olivier.tetard@miskin.fr>, 2015
 */

namespace OCA\Ownpad\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IConfig;

class DisplayController extends Controller {

    /** @var IURLGenerator */
    private $urlGenerator;

    /** @var IConfig */
    private $config;

    /**
     * @param string $AppName
     * @param IRequest $request
     * @param IURLGenerator $urlGenerator
     */
    public function __construct($AppName, IRequest $request, IURLGenerator $urlGenerator, IConfig $config) {
        parent::__construct($AppName, $request);
        $this->urlGenerator = $urlGenerator;
        $this->config = $config;
    }

    /**
     * @PublicPage
     * @NoCSRFRequired
     *
     * @return TemplateResponse
     */
    public function showPad($file, $dir) {
        /* Retrieve file content to find pad’s URL */
        $content = \OC\Files\Filesystem::file_get_contents($dir."/".$file);
        preg_match('/URL=(.*)$/', $content, $matches);
        $url = $matches[1];
        $title = $file;

        /*
         * Not totally sure that this is the right way to proceed…
         *
         * First we decode the URL (to avoid double encode), then we
         * replace spaces with underscore (as they are converted as
         * such by Etherpad), then we encode the URL properly (and we
         * avoid to urlencode() the protocol scheme).
         *
         * Magic urlencode() function was stolen from this answer on
         * StackOverflow: <http://stackoverflow.com/a/7974253>.
         */
        $url = urldecode($url);
        $url = str_replace(' ', '_', $url);
        $url = preg_replace_callback('#://([^/]+)/(=)?([^?]+)#', function ($match) {
            return '://' . $match[1] . '/' . $match[2] . join('/', array_map('rawurlencode', explode('/', $match[3])));
        }, $url);

        $params = [
            'urlGenerator' => $this->urlGenerator,
            'url' => $url,
            'title' => $title,
        ];

        // Check for valid URL
        // Get File-Ending
        $split = explode(".", $file);
        $fileending = $split[count($split)-1];

        // Get Host-URL
        if($fileending === "calc") {
            $host = \OCP\Config::getAppValue('ownpad', 'ownpad_ethercalc_host', false);
        }
        elseif($fileending === "pad") {
            $host = \OCP\Config::getAppValue('ownpad', 'ownpad_etherpad_host', false);
        }

        if(substr($host, -1, 1) != '/') {
            $host .= '/';
        }

        // Escape all RegEx-Characters
        $hostreg = preg_quote($host);
        // Escape all Slashes in URL to use in RegEx
        $hostreg = preg_replace("/\//", "\/", $host);

        // Final Regex-String
        if($fileending === "calc") {
            /*
             * Ethercalc documents with “multisheet” support starts
             * with a `=`.
             */
            $regex = "/^".$hostreg."=?[^\/]+$/";
        }
        elseif($fileending === "pad") {
            /*
             * Etherpad documents can contain special characters, for
             * “protected pads” for example.
             */
            $regex = "/^".$hostreg."p\/[^\/]+$/";
        }

        // Show the Pad, if URL is valid
        if (preg_match($regex, $url) == 1) {
            $response = new TemplateResponse($this->appName, 'viewer', $params, 'blank');
        }
        else {  // Show Error-Page
            $response = new TemplateResponse($this->appName, 'noviewer', $params, 'blank');
        }

        /*
         * Allow Etherpad and Ethercalc domains to the
         * Content-Security-frame- list.
         *
         * This feature was introduced in ownCloud 8.1.
         */
        $policy = new ContentSecurityPolicy();

        if($this->config->getAppValue('ownpad', 'ownpad_etherpad_enable', 'no') !== 'no') {
            $policy->addAllowedFrameDomain($this->config->getAppValue('ownpad', 'ownpad_etherpad_host', ''));
            $policy->addAllowedChildSrcDomain($this->config->getAppValue('ownpad', 'ownpad_etherpad_host', ''));
        }

        if($this->config->getAppValue('ownpad', 'ownpad_ethercalc_enable', 'no') !== 'no') {
            $policy->addAllowedFrameDomain($this->config->getAppValue('ownpad', 'ownpad_ethercalc_host', ''));
            $policy->addAllowedChildSrcDomain($this->config->getAppValue('ownpad', 'ownpad_ethercalc_host', ''));
        }

        $response->setContentSecurityPolicy($policy);

        return $response;
    }
}
