<?php
/*
$Id: show.php 833 2007-08-10 01:16:57Z gandon $
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003 David DELON
Copyright 2002, 2003 Charles NEPOTE
Copyright 2003  Eric DELORD
Copyright 2003  Eric FELDSTEIN
Copyright 2004  Jean Christophe ANDR?
Copyright 2005  Didier Loiseau
All rights reserved.
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:
1. Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
notice, this list of conditions and the following disclaimer in the
documentation and/or other materials provided with the distribution.
3. The name of the author may not be used to endorse or promote products
derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

// V?rification de s?curit?
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\CommentService;

// Generate page before displaying the header, so that it might interract with the header
ob_start();

echo '<div class="page"';
echo (($user = $this->GetUser()) && ($user['doubleclickedit'] == 'N') || !$this->HasAccess('write')) ? '' : ' ondblclick="doubleClickEdit(event);"';
echo '>'."\n";
if (!empty($_SESSION['redirects'])) {
    $trace = $_SESSION['redirects'];
    $tag = $trace[count($trace) - 1];
    $prevpage = $this->LoadPage($tag);
    echo '<div class="redirectfrom"><em>(Redirig&eacute; depuis ', $this->Link($prevpage['tag'], 'edit'), ")</em></div>\n";
    unset($_SESSION['redirects'][count($trace) - 1]);
}

if ($HasAccessRead=$this->HasAccess("read")) {
    if (!$this->page) {
        echo "Cette page n'existe pas encore, voulez vous la <a href=\"".$this->href("edit")."\">cr&eacute;er</a> ?" ;
    } else {
        // comment header?
        if ($this->page["comment_on"]) {
            echo "<div class=\"commentinfo\">Ceci est un commentaire sur ",$this->ComposeLinkToPage($this->page["comment_on"], "", "", 0),", post&eacute; par ",$this->Format($this->page["user"])," &agrave; ",$this->page["time"],"</div>";
        }

        if ($this->page["latest"] == "N") {
            echo '<div class="alert alert-info">'."\n";
            echo "Ceci est une version archiv&eacute;e de <a href=\"",$this->href(),"\">",$this->GetPageTag(),"</a> &agrave; ",$this->page["time"];
            // if this is an old revision, display some buttons
            if ($this->HasAccess("write")) {
                $latest = $this->LoadPage($this->tag); ?>
				<?php
                  $time = isset($_GET['time']) ? $_GET['time'] : '';
                echo $this->FormOpen(testUrlInIframe() ? 'editiframe' : 'edit'); ?>
				<input type="hidden" name="time" value="<?php echo $time ?>" />
				<input type="hidden" name="previous" value="<?php echo  $latest["id"] ?>" />
				<input type="hidden" name="body" value="<?php echo  htmlspecialchars($this->page["body"], ENT_COMPAT, YW_CHARSET) ?>" />
				<input class="btn btn-primary" type="submit" value="R&eacute;&eacute;diter cette version archiv&eacute;e" />
				<?php echo  $this->FormClose(); ?>
				<?php
            }

            echo '</div>'."\n";
        }

        // display page
        $this->RegisterInclusion($this->GetPageTag());
        echo $this->Format($this->page['body'], 'wakka', $this->GetPageTag());
        $this->UnregisterLastInclusion();
    }
} else {
    echo '<div class="alert alert-warning">'._t('NOT_AUTORIZED_TO_READ_PAGE').'</div>';
}
?>
<hr class="hr_clear" />
</div>


<?php
// Handle comments on page
$tag = $this->getPageTag();
$aclsService = $this->services->get(AclService::class);
$hasAccessComment = $aclsService->hasAccess('accescomment');
if ($HasAccessRead && $hasAccessComment && (!$this->page || !$this->page["comment_on"])) {
    $commentService = $this->services->get(CommentService::class);
    echo $commentService->getCommentList($tag);
    echo $commentService->getCommentForm($tag);
}
$content = ob_get_clean();
echo $this->Header();
echo $content;
echo $this->Footer();

?>
