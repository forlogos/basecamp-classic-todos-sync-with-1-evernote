<?php
//your contact email or URL
$contact='';

//your basecamp user info
$username  = '';
$password = '';
$account_id = '';
$basecamphq_url='';

//set UR: endpoints
$projects_URL='/projects.xml';
$todo_URL='/todo_lists.xml';

// Get cURL resource
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
    	$output[$pid]['url']=$project_url.'/todo_lists?utf8=✓&responsible_party='.$account_id;
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
$resp = curl_exec($curl);

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

//let's startoutput as html
foreach($output as $o) {
	$project_url=$o['project_url'];
	echo '<a href="'.$o['url'].'">'.$o['projectname'].'</a><br/>
	<ul>';
		$lists=(!empty($o['lists'])?$o['lists']:array());
		foreach($lists as $l) {
			echo '<li>'.$l['listname'].'
				<ul>';
					foreach($l['item']as $it) {
						echo '<li><a href="'.$project_url.'/todo_items/'.$it['id'].'/comments">'.$it['content'].'</a></li>';
					}
			echo '</ul></li>';
		}
	echo '</ul>';
}
?>