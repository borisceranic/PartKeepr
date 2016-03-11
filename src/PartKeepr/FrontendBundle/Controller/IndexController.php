<?php

namespace PartKeepr\FrontendBundle\Controller;

use Doctrine\ORM\NoResultException;
use PartKeepr\AuthBundle\Entity\User\User;
use PartKeepr\PartKeepr;
use PartKeepr\Session\SessionManager;
use PartKeepr\Util\Configuration;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\Version as ORMVersion;
use Doctrine\DBAL\Version as DBALVersion;
use Doctrine\Common\Version as DoctrineCommonVersion;

class IndexController extends Controller
{
    /**
     * This is basically a copy of the PartKeepr's legacy index.php
     * @Route("/")
     */
    public function indexAction()
    {
        PartKeepr::initialize("");

        $this->legacyAuthStuff();

        $aParameters = array();
        $aParameters["doctrine_orm_version"] = ORMVersion::VERSION;
        $aParameters["doctrine_dbal_version"] = DBALVersion::VERSION;
        $aParameters["doctrine_common_version"] = DoctrineCommonVersion::VERSION;
        $aParameters["php_version"] = phpversion();

        $maxPostSize = PartKeepr::getBytesFromHumanReadable(ini_get("post_max_size"));
        $maxFileSize = PartKeepr::getBytesFromHumanReadable(ini_get("upload_max_filesize"));

        $aParameters["maxUploadSize"] = min($maxPostSize, $maxFileSize);

        if (!extension_loaded('gd'))
        {
            // @todo This check is deprecated and shouldn't be done here. Sf2 should automatically take care of this

            return $this->render('PartKeeprFrontendBundle::error.html.twig',
                array(
                    "title" => PartKeepr::i18n("GD2 is not installed"),
                    "error" => PartKeepr::i18n(
                        "You are missing the GD2 extension. Please install it and restart the setup to verify that the library was installed correctly."
                    ),
                )
            );
        }

        $aParameters["availableImageFormats"] = $this->getSupportedImageFormats();

        /* Automatic Login */
        if (Configuration::getOption("partkeepr.frontend.autologin.enabled", false) === true) {
            $aParameters["autoLoginUsername"] = Configuration::getOption("partkeepr.frontend.autologin.username");
            $aParameters["autoLoginPassword"] = Configuration::getOption("partkeepr.frontend.autologin.password");
        }

        if (Configuration::getOption("partkeepr.frontend.motd", false) !== false) {
            $aParameters["motd"] = Configuration::getOption("partkeepr.frontend.motd");
        }

        $renderParams = array();
        $renderParams["debug_all"] = Configuration::getOption("partkeepr.frontend.debug_all", false);
        $renderParams["debug"] = Configuration::getOption("partkeepr.frontend.debug", false);
        $renderParams["parameters"] = $aParameters;

        if (isset($_SERVER['HTTPS'])) {
            $renderParams["https"] = true;
        } else {
            $renderParams["https"] = false;
        }


        $renderParams["models"] = $this->copyModels();

        return $this->render('PartKeeprFrontendBundle::index.html.twig', $renderParams);
    }

    protected function getSupportedImageFormats()
    {
        $list = array();

        if (imagetypes() & IMG_GIF)  { $list[] = 'GIF';  }
        if (imagetypes() & IMG_JPG)  { $list[] = 'JPG';  }
        if (imagetypes() & IMG_JPEG) { $list[] = 'JPEG'; }
        if (imagetypes() & IMG_PNG)  { $list[] = 'PNG';  }
        if (imagetypes() & IMG_WBMP) { $list[] = 'WBMP'; }
        if (imagetypes() & IMG_XPM)  { $list[] = 'XPM';  }

        return $list;
    }

    /**
     * Copies all generated models to the frontend directory
     *
     * @todo Refactor to auto-generate models to the correct directory. This is a workaround.
     */
    protected function copyModels()
    {
        $cacheDir = $this->get("kernel")->getCacheDir();

        $target = $this->get("kernel")->getRootDir()."/../web/bundles/doctrinereflection/";
        @mkdir($target, 0777, true);

        $finder = new Finder();
        $finder->files()->in($cacheDir."/doctrinereflection/");

        $models = array();

        foreach ($finder as $file) {
            /** @var SplFileInfo $file */
            copy($file->getRealPath(), $target.$file->getBasename());
            $models[] = "bundles/doctrinereflection/".$file->getBasename();
        }

        return $models;
    }

    protected function legacyAuthStuff()
    {
        /* HTTP auth */
        if (Configuration::getOption("partkeepr.auth.http", false) === true) {
            if (!isset($_SERVER["PHP_AUTH_USER"])) {
                // @todo Redirect to permission denied page
                die("Permission denied");
            }

            try {
                $user = User::loadByName($_SERVER['PHP_AUTH_USER']);
            } catch (NoResultException $e) {
                $user = new User;
                $user->setUsername($_SERVER['PHP_AUTH_USER']);
                $user->setPassword("invalid");

                PartKeepr::getEM()->persist($user);
                PartKeepr::getEM()->flush();
            }


            $session = SessionManager::getInstance()->startSession($user);

            $aParameters["autoLoginUsername"] = $user->getUsername();
            $aParameters["auto_start_session"] = $session->getSessionID();

            $aPreferences = array();

            foreach ($user->getPreferences() as $result) {
                $aPreferences[] = $result->serialize();
            }

            $aParameters["userPreferences"] = array("response" => array("data" => $aPreferences));
        }

    }
}
