<?php //////////////////////////////////
//Basecamp settings
//your contact email or URL
$contact='';

//basecamp user info
$username  = '';
$password = '';
$account_id = '';//get this from the URL when you view 'My info'
$basecamphq_url='';//usually https://companyname.basecamphq.com

//////////////////////////////////
//Evernote settings

//your developer token. More info: https://dev.evernote.com/doc/articles/authentication.php#devtoken
$developerToken = "";

//path to evernote autoload.php, from the SDK https://github.com/evernote/evernote-cloud-sdk-php
$evernote_autoload='';// like 'evernote/'

//the unique ID for the note you want updated. get this from URL when viewing you note in its own window on the evernote web app
//https://sandbox.evernote.com/view/notebook/this-is-the-guid
//everything after a ? or # is not part of the guid
$note_guid ='';

//evernote sandbox or no? set this to false to use in production
$sandbox = true;

//settings done, on to using them with the APIs


//////////////////////////////////////////////////////////////////////////////////////////////////////
//Get todos from BaseCamp

//set URL: endpoints for basecamp
$projects_URL='/projects.xml';
$todo_URL='/todo_lists.xml';

//Get cURL resource
$curl = curl_init();

//projects: Set some curl options
curl_setopt_array($curl, array(
    CURLOPT_USERPWD => $username.':'.$password,
    CURLOPT_USERAGENT => $contact,
    CURLOPT_URL => $basecamphq_url.$projects_URL,
    CURLOPT_HTTPHEADER => array('Content-type: application/xml', 'Accept: application/xml'),
    CURLOPT_RETURNTRANSFER => 1  
));

//projects: Send the request & save response to $resp
$resp = curl_exec($curl);

//projects: parse xml data into arrays
$projects=simplexml_load_string($resp);

//set a blank var. we'll save everything to it for later
$output=array();

//parse information for each project, add vars to $output array
foreach ($projects as $p) {
    if($p->status=='active') {
    	//cast values
    	$name=(string)$p->name[0];
    	$pid=(string)$p->id[0];
    	$project_url=$basecamphq_url.'/projects/'.$p->id.'-'.trim(strtolower(str_replace(' ','-',$p->name)));

    	//save to $output
    	$output[$pid]['url']=$project_url.'/todo_lists?responsible_party='.$account_id;
    	$output[$pid]['projectname']=$name;
    	$output[$pid]['project_url']=$project_url;
    }
}

//set curl options to get todos for specified user
curl_setopt_array($curl, array(
	CURLOPT_USERPWD => $username.':'.$password,
    CURLOPT_USERAGENT => $contact,
	CURLOPT_URL => $basecamphq_url.$todo_URL.'?responsible_party='.$account_id,
    CURLOPT_HTTPHEADER => array('Content-type: application/xml', 'Accept: application/xml'),
    CURLOPT_RETURNTRANSFER => 1
));

//todos: Send the request & save response to $resp
$resp=curl_exec($curl);

//Close curl processes to clear up some resources
curl_close($curl);

//todos: parse xml data into arrays
$todos=simplexml_load_string($resp);

//set these string vars, will use them to get object props because I can't directly call on them thru object type because of the '-'
$pid_str='project-id';
$todo_items_str='todo-items';
$todo_item_str='todo-item';

//loop thru curl response todo lists
foreach($todos as $t) {
	//cast values
	$pid=(string)$t->$pid_str;
	$list_id=(string)$t->id[0];
	$listname=(string)$t->name[0];
	//save to output
	$output[$pid]['lists'][$list_id]['listname']=$listname;
	//prep var for foreach
	$todos=$t->$todo_items_str;
	//loop thru each todo item in list
	foreach($todos->$todo_item_str as $tdi) {
		//cast values
		$todo_id=(string)$tdi->id[0];
		$content=(string)$tdi->content[0];
		//save to $output
		$output[$pid]['lists'][$list_id]['item'][$todo_id]['content']=$content;
		$output[$pid]['lists'][$list_id]['item'][$todo_id]['id']=$todo_id;
	}
}

//////////////////////////////////
//format output/content
$new_note_content='';
//make output and save it to $new_note_content
foreach($output as $o) {
	$project_url=$o['project_url'];
	$url=$o['url'];
	$projectname=$o['projectname'];
	$new_note_content.='<a href="'.$url.'">'.$projectname.'</a><br/>
	<ul>';
		$lists=(!empty($o['lists'])?$o['lists']:array());
		foreach($lists as $l) {
			$new_note_content.='<li>'.$l['listname'].'<ul>';
					foreach($l['item']as $it) {
						$id=$it['id'];
						$content=(string)htmlspecialchars($it['content']);
						$new_note_content.='<li><a href="'.$project_url.'/todo_items/'.$id.'/comments">'.$content.'</a></li>';
					}
			$new_note_content.='</ul></li>';
		}
	$new_note_content.='</ul>';
}

//////////////////////////////////
//Do Evernote stuff now
//require evernote SDK
require_once $evernote_autoload.'autoload.php';

//get evernote client
$client = new \Evernote\Client($developerToken, $sandbox);

//get the note object
$note=$client->getNote($note_guid);

//make an identical note object, with a different name
$updated_note=$note;

//set updated content
$updated_note->content=$new_note_content;

//save changes
$client->replaceNote($note, $updated_note);
//Done!!

//////////////////////////////////
//show the content that was saved
echo $new_note_content;
?>
