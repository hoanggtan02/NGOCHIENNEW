<?php
    $jatbi = $app->getValueData('jatbi');
    $app->addHook('POST-upload-file-after-insert', function($data) use($jatbi,$app) {
        $getProposal = $app->get("proposals","*",["active"=>$app->xss($_GET['proposal']),"deleted"=>0]);
        if($getProposal>1){
            $proposal = [
                "proposal" => $getProposal['id'],
                "files" => $data['id'],
            ];
            $app->insert("proposal_files",$proposal);
        }
        $insert = [
            "account" => $data['account'],
            "type" => 'files',
            "data" => $data['id'],
            "date" => date("Y-m-d H:i:s"),
            "modify" => date("Y-m-d H:i:s"),
            "access" => 0,
            "active" => $jatbi->active(),
            "url" => $app->randomString(128),
            "permission" => 1,
        ];
        $app->insert("files_shares",$insert);
        $app->update("files",["permission"=>1],["id"=>$data['id']]);
        $jatbi->logs('proposal','proposal-files',[$data,$proposal]);
    }, 10);
 ?>