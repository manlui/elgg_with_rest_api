<?php
/**
 * @param $guid
 * @param $text
 * @param $username
 * @return mixed
 * @throws InvalidParameterException
 */
function file_post_comment($guid, $text, $username)
{

	$user = get_user_by_username($username);
	if (!$user) {
		throw new InvalidParameterException('registration:usernamenotvalid');
	}

	if ($guid) {
		$entity = get_entity($guid);
	}

	if ($entity) {
		$return['success'] = false;
		if (empty($text)) {
			$return['message'] = elgg_echo("thefilecomment:blank");
			return $return;
		}

		if ($entity) {
			$comment = new ElggComment();
			$comment->description = $text;
			$comment->owner_guid = $user->guid;
			$comment->container_guid = $entity->guid;
			$comment->access_id = $entity->access_id;
			$guid_comment = $comment->save();

			if ($guid_comment) {
				$return['success'] = $guid_comment;
				elgg_create_river_item(array(
					'view' => 'river/object/comment/create',
					'action_type' => 'comment',
					'subject_guid' => $user->guid,
					'object_guid' => $guid_comment,
					'target_guid' => $entity->guid,
				));
			}
		}

		return $return;
	} else {
		$return['success'] = false;
		$return['message'] = 'Require guid from post';

		return $return;
	}
}

elgg_ws_expose_function('file.post_comment',
		"file_post_comment",
		array(	'guid' => array ('type' => 'int', 'required' => true),
				'text' => array ('type' => 'string', 'required' => true),
				'username' => array ('type' => 'string', 'required' => true),
		),
		"Post a comment on a file post",
		'POST',
		true,
		true);