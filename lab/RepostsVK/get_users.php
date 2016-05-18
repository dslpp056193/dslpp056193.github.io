<?
	// ПАРСИМ СПИСОК ГРУПП

	$_GROUPS = urldecode($_GET['groups']);
	$_GROUPS = trim($_GROUPS);
	$_GROUPS = explode(',', $_GROUPS);

	foreach ($_GROUPS as $INDEX => $_GROUP) {
		$_GROUP_POS = strrpos($_GROUP, '.com/') + 5;
		$_GROUP = substr($_GROUP, $_GROUP_POS);
		$_GROUPS[$INDEX] = $_GROUP;
	}

	// ПОЛУЧАЕМ И ПРЕОБРАЗУЕМ ССЫЛКУ

	$method = 'https://api.vk.com/method/';

	$link = urldecode($_GET['link']);
	$link_pos = strrpos($link, 'wall') + 4;
	$link = substr($link, $link_pos);
	$link_data = explode('_', $link);

	$owner_id = $link_data[0];
	$post_id = $link_data[1];

	$ch = curl_init();

	// ПОЛУЧЕНИЕ СПИСКА РЕПОСТНУВШИХ ПОЛЬЗОВАТЕЛЕЙ
	// ПОЛУЧИТЬ СПИСОК ПЕРВОЙ ТЫСЯЧИ ПОЛЬЗОВАТЕЛЕЙ
	$users_offset = 0;

	curl_setopt_array($ch, array(
		CURLOPT_URL => $method."likes.getList?type=post&owner_id=".$owner_id."&item_id=".$post_id."&count=1000&filter=copies&offset=".$users_offset,
		CURLOPT_VERBOSE => true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_RETURNTRANSFER => true
	));

	// МАССИВ ДЛЯ ОКОНЧАТЕЛЬНОГО СПИСКА ДАННЫХ ПОЛЬЗОВАТЕЛЕЙ
	$users = array();

	$users_r = json_decode( curl_exec($ch), true );
	$users_r_count = $users_r['response']['count'];
	$users_r = $users_r['response']['users'];

	// РЕКУРСИЯ ДЛЯ ПОЛУЧЕНИЯ ОСТАЛЬНЫХ ТЫСЯЧ (ОБХОД ОГРАНИЧЕНИЯ)

	for ($i=1; $i < ceil( $users_r_count / 1000 ); $i++) {
		$users_offset = $i * 1000;

		curl_setopt_array($ch, array(
			CURLOPT_URL => $method."likes.getList?type=post&owner_id=".$owner_id."&item_id=".$post_id."&count=1000&filter=copies&offset=".$users_offset,
			CURLOPT_VERBOSE => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_RETURNTRANSFER => true
		));

		$new_users = json_decode( curl_exec($ch), true );

		$users_r = array_merge($users_r, $new_users['response']['users']);
	}

	// ОТСЕИВАЕМ ЮЗЕРОВ, КОТОРЫЕ НЕ СОСТОЯТ В ГРУППАХ

	$members = $users_r;

	if( $_GET['groups'] ){

		foreach ($_GROUPS as $INDEX => $group) {

			$members_it = array();

			// ПОЛУЧАЕМ ДАННЫЕ О СОСТОЯЩИХ И НЕ СОСТОЯЩИХ ЮЗЕРАХ

			for ($i=0; $i < ceil( $users_r_count / 400 ); $i++) {

				$f_user = array_slice($users_r, $i * 400, 400);

				$array_string = '';

				foreach ($f_user as $INDEX => $user) {
					if($user < 0) $user = $user * -1;
					$array_string = $array_string.','.$user;
				}

				// ПОЛУЧИТЬ ИНФУ О ЧЛЕНСТВЕ ЮЗЕРОВ

				curl_setopt_array($ch, array(
					CURLOPT_URL => $method.'groups.isMember?group_id='.$group.'&user_ids='.$array_string,
					CURLOPT_VERBOSE => true,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_SSL_VERIFYPEER => false,
					CURLOPT_RETURNTRANSFER => true
				));

				$member_users = json_decode( curl_exec($ch), true );

				if( is_array($member_users['response']) ){
					$members_it = array_merge($members_it, $member_users['response']);
				}
				
			}

			// ОТСЕИВАЕМ ТЕХ, КТО НЕ СОСТОИТ В ГРУППЕ

			foreach ($members_it as $INDEX => $member) {
				if($member['member'] == 0){
					unset($members[$INDEX]);
				}
			}

		}		

	}

	// ВСЕ ПОЛЬЗОВАТЕЛИ ПОДРОБНО

	$members_desc = array();


	// ПОЛУЧИМ ПОДРОБНУЮ ИНФУ ВСЕХ ПОЛЬЗОВАТЕЛЕЙ

	for ($i=0; $i < ceil( count($members) / 300 ); $i++) {

		$f_user = array_slice($members, $i * 300, 300);

		$array_string = '';

		foreach ($f_user as $INDEX => $user) {
			if($user < 0) $user = $user * -1;
			$array_string = $array_string.','.$user;
		}

		curl_setopt_array($ch, array(
			CURLOPT_URL => $method."users.get?fields=photo_max_orig,sex&user_ids=".$array_string,
			CURLOPT_VERBOSE => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_RETURNTRANSFER => true
		));

		$new_array = json_decode( curl_exec($ch), true );
		
		$members_desc = array_merge($members_desc, $new_array['response']);

	}

	curl_close($ch);
				
	header('Content-Type: application/json');
	echo json_encode($members_desc);
?>