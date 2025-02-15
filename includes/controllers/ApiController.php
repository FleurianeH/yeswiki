<?php

namespace YesWiki\Core\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\DiffService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiController;

class ApiController extends YesWikiController
{
    /**
     * @Route("/api",options={"acl":{"public"}})
     */
    public function getDocumentation()
    {
        $output = $this->wiki->Header();

        $output .= '<h1>YesWiki API</h1>';

        $urlUser = $this->wiki->Href('', 'api/user');
        $output .= '<h2>'._t('USERS').'</h2>'."\n".
            'GET <code><a href="'.$urlUser.'">'.$urlUser.'</a></code><br />';

        $urlGroup = $this->wiki->Href('', 'api/group');
        $output .= '<h2>'._t('GROUPS').'</h2>'."\n".
            'GET <code><a href="'.$urlGroup.'">'.$urlGroup.'</a></code><br />';
        
        $urlPages = $this->wiki->Href('', 'api/pages');
        $output .= '<h2>'._t('PAGES').'</h2>'."\n".
            'GET <code><a href="'.$urlPages.'">'.$urlPages.'</a></code><br />';

        // TODO use annotations to document the API endpoints
        $extensions = $this->wiki->extensions;
        foreach ($this->wiki->extensions as $extension => $pluginBase) {
            $response = null ;
            if (file_exists($pluginBase . 'controllers/ApiController.php')) {
                $apiClassName = 'YesWiki\\' . ucfirst($extension) . '\\Controller\\ApiController';
                if (!class_exists($apiClassName, false)) {
                    include($pluginBase . 'controllers/ApiController.php') ;
                }
                if (class_exists($apiClassName, false)) {
                    $apiController = new $apiClassName() ;
                    $apiController->setWiki($this->wiki);
                    if (method_exists($apiController, 'getDocumentation')) {
                        $response = $apiController->getDocumentation() ;
                    }
                }
            }
            if (empty($response)) {
                $func = 'documentation'.ucfirst(strtolower($extension));
                if (function_exists($func)) {
                    $output .= $func();
                }
            } else {
                $output .= $response ;
            }
        }

        $output .= $this->wiki->Footer();

        return new Response($output);
    }

    /**
     * @Route("/api/user/{userId}")
     */
    public function getUser($userId)
    {
        $this->denyAccessUnlessAdmin();

        return new ApiResponse($this->getService(UserManager::class)->getOne($userId));
    }

    /**
     * @Route("/api/user")
     */
    public function getAllUsers()
    {
        $this->denyAccessUnlessAdmin();

        return new ApiResponse($this->getService(UserManager::class)->getAll());
    }

    /**
     * @Route("/api/group")
     */
    public function getAllGroups()
    {
        $this->denyAccessUnlessAdmin();

        return new ApiResponse($this->wiki->GetGroupsList());
    }
    
    /**
     * @Route("/api/pages",options={"acl":{"public"}})
     */
    public function getAllPages()
    {
        $dbService = $this->getService(DbService::class);
        $aclService = $this->getService(AclService::class);
        // recuperation des pages wikis
        $sql = 'SELECT * FROM '.$dbService->prefixTable('pages');
        $sql .= ' WHERE latest="Y" AND comment_on="" AND tag NOT LIKE "LogDesActionsAdministratives%" ';
        $sql .= ' AND tag NOT IN (SELECT resource FROM '.$dbService->prefixTable('triples').' WHERE property="http://outils-reseaux.org/_vocabulary/type") ';
        $sql .= ' ORDER BY tag ASC';
        $pages = _convert($dbService->loadAll($sql), 'ISO-8859-15');
        $pages = array_filter($pages, function ($page) use ($aclService) {
            return $aclService->hasAccess('read', $page["tag"]);
        });
        $pagesWithTag = [];
        foreach ($pages as $page) {
            $pagesWithTag[$page['tag']] = $page;
        }
        return new ApiResponse(empty($pagesWithTag) ? null : $pagesWithTag);
    }

    /**
     * @Route("/api/pages/{tag}",options={"acl":{"public"}})
     */
    public function getPage(Request $request, $tag)
    {
        ob_start(); // to catch error messages
        $this->denyAccessUnlessGranted('read', $tag);
        
        $pageManager = $this->getService(PageManager::class);
        $diffService = $this->getService(DiffService::class);
        $entryManager = $this->getService(EntryManager::class);
        $entryController = $this->getService(EntryController::class);
        $page = $pageManager->getOne($tag, $request->get('time'));
        if (!$page) {
            return new ApiResponse(null, Response::HTTP_NOT_FOUND);
        }
        
        if ($entryManager->isEntry($page['tag'])) {
            $page['html'] = $entryController->view($page['tag'], $page['time'], false);
            $page['code'] = $diffService->formatJsonCodeIntoHtmlTable($page);
        } else {
            $page['html'] = $this->wiki->Format($page["body"], 'wakka', $page['tag']);
            $page['code'] = $page['body'];
        }

        if ($request->get('includeDiff')) {
            $prevVersion = $pageManager->getPreviousRevision($page);
            if (!$prevVersion) {
                $prevVersion = ["tag" => $tag, "body" => "", "time" => null];
            }
            $page['commit_diff_html'] = $diffService->getPageDiff($prevVersion, $page, true);
            $page['commit_diff_code'] = $diffService->getPageDiff($prevVersion, $page, false);

            $lastVersion = $pageManager->getOne($page['tag']);
            $page['diff_html'] = $diffService->getPageDiff($lastVersion, $page, true);
            $page['diff_code'] = $diffService->getPageDiff($lastVersion, $page, false);
        }

        $errors = ob_get_contents();
        ob_end_clean();
        return new ApiResponse((empty($errors) ? [] : ['errors' => $errors])+$page);
    }
}
