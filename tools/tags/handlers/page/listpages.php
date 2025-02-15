<?php
/*
listepages.php

Copyright 2009  Florian SCHMITT
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

use YesWiki\Tags\Service\TagsManager;

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

// fonctions a inclure
include_once 'tools/tags/libs/tags.functions.php';

$tagsManager = $this->services->get(TagsManager::class);

// recuperation de tous les parametres
$tags = (isset($_GET['tags'])) ? $_GET['tags'] : '';
$type = (isset($_GET['type'])) ? $_GET['type'] : '';
$lienedit = (isset($_GET['lienedit'])) ? $_GET['lienedit'] : '';
$class = (isset($_GET['class'])) ? $_GET['class'] : 'liste';
$nb = (isset($_GET['nb'])) ? $_GET['nb'] : '';
$tri = (isset($_GET['tri'])) ? $_GET['tri'] : '';
$nbcartrunc = 200;
$template = (isset($_GET['template'])) ? $_GET['template'] : 'pages_accordion.tpl.html';
$valtemplate=array();

$output = '';

// creation de la liste des mots cles a filtrer
$this->AddJavascriptFile('tools/tags/libs/tag.js');
$tab_selected_tags = explode(',', $tags);
$selectiontags = ' AND value IN ("'.implode(",", $tab_selected_tags).'")';

// on recupere tous les tags existants
$sql = 'SELECT DISTINCT value FROM '.$this->config['table_prefix'].'triples WHERE property="http://outils-reseaux.org/_vocabulary/tag" ORDER BY value ASC';
$tab_tous_les_tags = $this->LoadAll($sql);
$tab_tag = array();
if (is_array($tab_tous_les_tags)) {
    foreach ($tab_tous_les_tags as $tag) {
        $tag['value'] = _convert(stripslashes($tag['value']), 'ISO-8859-1');
        if (in_array($tag['value'], $tab_selected_tags)) {
            $tab_tag[] = '&nbsp;<a class="tag-label label label-primary label-active" href="'.$this->href('listpages', $this->GetPageTag(), 'tags='.urlencode($tag['value'])).'">'.$tag['value'].'</a>'."\n";
        } else {
            $tab_tag[] = '&nbsp;<a class="tag-label label label-info" href="'.$this->href('listpages', $this->GetPageTag(), 'tags='.urlencode($tag['value'])).'">'.$tag['value'].'</a>'."\n";
        }
    }
    $outputselecttag = '';
    if (!empty($tab_tag)) {
        $outputselecttag .= '<strong><i class="icon icon-tags"></i> '._t('TAGS_FILTER').' : </strong>';
        foreach ($tab_tag as $tag) {
            $outputselecttag .= $tag;
        }
    }
}



$text = '';
// affiche le resultat de la recherche
$resultat = $tagsManager->getPagesByTags($tags, $type, $nb, $tri, $template, $class, $lienedit);
if ($resultat) {
    $aclService = $this->services->get(\YesWiki\Core\Service\AclService::class);
    $element = [];
    foreach ($resultat as $page) {
        if ($aclService->hasAccess('read', $page['tag'])) {
            $element[$page['tag']]['tagnames'] = '';
            $element[$page['tag']]['tagbadges'] = '';
            $element[$page['tag']]['body'] = $page['body'];
            $element[$page['tag']]['owner'] = $page['owner'];
            $element[$page['tag']]['user'] = $page['user'];
            $element[$page['tag']]['time'] = $page['time'];
            $element[$page['tag']]['title'] = get_title_from_body($page);
            $element[$page['tag']]['image'] = get_image_from_body($page);
            $element[$page['tag']]['desc'] = tokenTruncate(strip_tags($this->Format($page['body'], 'wakka', $page["tag"])), $nbcartrunc);
            $pagetags = $this->GetAllTriplesValues($page['tag'], 'http://outils-reseaux.org/_vocabulary/tag', '', '');
            foreach ($pagetags as $tag) {
                $element[$page['tag']]['tagnames'] .= sanitizeEntity($tag['value']).' ';
                $element[$page['tag']]['tagbadges'] .= '<span class="tag-label label label-primary">'.$tag['value'].'</span>&nbsp;';
            }
        }
    }
    $text .= $this->render("@tags/$template", ['elements' => $element ]);
    $nb_total = count($element);
} else {
    $nb_total = 0;
}

$output .= '<div class="alert alert-info">'."\n";
if ($nb_total > 1) {
    $output .= 'Un total de '.$nb_total.' pages ont &eacute;t&eacute; trouv&eacute;es';
} elseif ($nb_total == 1) {
    $output .= 'Une page a &eacute;t&eacute; trouv&eacute;e';
} else {
    $output .= 'Aucune page trouv&eacute;e';
}
$output .= (!empty($tab_selected_tags) ? ' avec le mot cl&eacute; '.implode(' et ', array_map(function ($tagName) {
    return '<span class="tag-label label label-info">'.$tagName.'</span>';
}, $tab_selected_tags)) : '').'.';
$output .= $this->Format('{{rss tags="'.$tags.'" class="pull-right"}}')."\n";
$output .= '</div>'."\n".$text;

echo $this->Header();
echo "<div class=\"page\">\n$output\n$outputselecttag\n<hr class=\"hr_clear\" />\n</div>\n";
echo $this->Footer();
