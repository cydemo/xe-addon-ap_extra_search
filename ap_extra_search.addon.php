<?php

/**
 * @file ap_extra_search.addon.php
 * @author cydemo <cydemo@gmail.com>
 */

if ( !defined('__XE__') ) return;

if ( $called_position === 'after_module_proc' && $this->module === 'board' && $this->act === 'dispBoardContent' )
{
	if ( array_map('trim', explode(',', $addon_info->position))[0] === '' )
	{
		return;
	}
	// 검색 방식 설정
	$addon_info->type = $addon_info->type ? $addon_info->type : 'and';
	$addon_info->type_multi = $addon_info->type_multi ? $addon_info->type_multi : 'or';
	$addon_info->select2radio = $addon_info->select2radio ? $addon_info->select2radio : 'N';
	$addon_info->basic = $addon_info->basic ? $addon_info->basic : 'N';
	$addon_info->signature = $addon_info->signature ? $addon_info->signature : 'N';
	$addon_info->range_search = $addon_info->range_search ? $addon_info->range_search : 'N';

	// document 모듈의 모델 호출
	$oDocumentModel = getModel('document');

	// 현재 모듈의 확장변수 사용자정의 호출
	$_extra_keys = $oDocumentModel->getExtraKeys($this->module_srl);

	// document 모듈 쿼리 실행을 위한 기본 설정값 지정
	$module_info = $this->module_info;
	$args = new stdClass();
	$args->module_srl = $module_info->module_srl;
	$args->category_srl = Context::get('category');
	$args->status = 'PUBLIC';

	// 문서 목록 작업을 위한 배열 변수 정의
	$cond = array();
	$extra_keys = array();
	$srl_list = array();
	$is_search = false;
	$search_session = false;

	// 애드온이 게시판 기본검색까지도 다중검색에 포함하는 경우
	if ( $addon_info->basic === 'Y' )
	{
		// 기본검색의 검색대상에서 확장변수를 제거
		Context::set('search_option', array_intersect_key
			(
				Context::get('search_option'), array_flip($this->search_option)
			)
		);
		// 기본검색의 쿼리 실행
		if ( Context::get('search_target') && Context::get('search_keyword') )
		{
			$query_id = 'addons.ap_extra_search.getDocumentList';
			$search_target = Context::get('search_target');
			$search_keyword =  Context::get('search_keyword');
			switch($search_target)
			{
				case 'title_content' :
					$search_keyword = str_replace(' ', '%', $search_keyword);
					$args->s_title = $search_keyword;
					$args->s_content = $search_keyword;
					break;
				case 'comment' :
					$args->s_comment = $search_keyword;
					$query_id = 'addons.ap_extra_search.getDocumentListWithinComment';
					break;
				case 'tag' :
					$args->s_tags = str_replace(' ', '%', $search_keyword);
					break;
				default :
					$search_keyword = str_replace(' ', '%', $search_keyword);
					$args->{'s_'.$search_target} = $search_keyword;
					break;
			}
			$srl_list = executeQueryArray($query_id, $args)->data;
			$search_session = true;
		}
		$is_search = true;
		Context::set('search_keyword', str_replace('%', ' ', $search_keyword));
	}

	// 현재 모듈에 사용자정의된 확장변수가 있을 때만 실행
	if ( count($_extra_keys) )
	{
		$is_range_date = false;
		$is_range_data = false;

		// 확장변수 다중검색
		foreach ($_extra_keys as $key => $val)
		{
			// 파라미터에 지정된 변수값을 $cond에 저장
			if ( Context::get('extra_vars' . $key) )
			{
				$cond[$key] = Context::get('extra_vars' . $key);
			}

			// 검색 가능한 확장변수만 $extra_keys에 저장 (이후 before_display_content 호출 시점에 재활용)
			if ( $val->search === 'Y' )
			{
				// 범위 검색 적용되는 변수 여부 확인
				if ( $addon_info->range_search === 'Y' && in_array($val->eid, array_map('trim', explode(',', $addon_info->range_search_target))) )
				{
					if ( $val->type === 'date' )
					{
						$is_range_date = true;
					}
					else
					{
						$is_range_data = true;
						$args->var_eid = $eid = $val->eid;
						$max_min = executeQueryArray('addons.ap_extra_search.getMaxAndMinValueWithinExtraVars', $args)->data;
						$val->max = $max_min[0]->max;
						$val->min = $max_min[0]->min;
					}
				}

				$is_search = true;
				$extra_keys[$key] = $val;
			}

			// 파라미터에 변수값이 있는 경우에만 검색된 document 정보를 $srl_list에 저장
			if ( $cond[$key] )
			{
				// 해당 키값을 기본 설정값으로 저장, 그리고 해당 변수값이 담긴 문서의 총 개수를 구해서, 목록 개수 변수값으로 저장
				$args->var_idx = $key;

				// 확장변수 변수값이 배열인 경우 (즉, 다중선택 체크박스인 경우)
				if ( is_array($cond[$key]) )
				{
					// 배열을 문자열로 바꿔 변수값을 서버에 저장. 이 값은 페이지 뒤로가기나 앞으로가기를 할 때 기억됨
					$val->setValue(implode('|@|', $cond[$key]));

					// AND 검색인 경우
					if ( $addon_info->type === 'and' )
					{
						// 기본 검색은 AND이지만 다중선택 변수는 OR 검색으로 설정한 경우
						if ( $addon_info->type_multi === 'or' )
						{
							// 다중선택 형식 확장변수의 OR 검색 결과값으로 이뤄진 별도의 문서 목록 생성
							$_srl_list = array();
							foreach ( $cond[$key] as $k => $v )
							{
								$args->var_value = $v;
								if ( in_array($val->type, array('radio', 'select')) )
								{
									$srls = executeQueryArray('addons.ap_extra_search.getDocumentListWithExtraVarsEqual', $args)->data;
								}
								else
								{
									$srls = executeQueryArray('addons.ap_extra_search.getDocumentListWithExtraVars', $args)->data;
								}

								// OR 검색에 맞게 중복 배열을 정리
								$_srl_list = array_merge($_srl_list, $srls);
								$_srl_list = array_values(array_map(
									'unserialize',
									array_unique(
										array_map(
											'serialize',
											$_srl_list
										)
									)
								));
							}

							// 출력할 문서 목록이 비어 있고 확장변수 검색 세션에서 현재 변수가 처음일 경우, 별도의 문서 목록 $_srl_list를 출력용 문서 목록 $srl_list에 병합
							if ( $srl_list === array() && $search_session === false )
							{
								$srl_list = array_merge($srl_list, $_srl_list);
								$search_session = true;
							}

							// AND 검색에 맞게 중복 배열을 정리
							$srl_list = array_values(array_map(
								'unserialize',
								array_intersect(
									array_map(
										'serialize',
										$srl_list
									),
									array_map(
										'serialize',
										$_srl_list
									)
								)
							));
						}
						// 기본 검색과 마찬가지로 다중선택 변수도 AND 검색으로 설정한 경우
						else
						{
							foreach ( $cond[$key] as $k => $v )
							{
								$args->var_value = $v;
								if ( in_array($val->type, array('radio', 'select')) )
								{
									$srls = executeQueryArray('addons.ap_extra_search.getDocumentListWithExtraVarsEqual', $args)->data;
								}
								else
								{
									$srls = executeQueryArray('addons.ap_extra_search.getDocumentListWithExtraVars', $args)->data;
								}

								// 출력할 문서 목록이 비어 있고 확장변수 검색 세션에서 현재 변수가 처음일 경우, 검색 결과값 $srls를 출력용 문서 목록 $srl_list에 병합
								if ( $srl_list === array() && $search_session === false )
								{
									$srl_list = array_merge($srl_list, $srls);
									$search_session = true;
								}

								// AND 검색에 맞게 중복 배열을 정리
								$srl_list = array_values(array_map(
									'unserialize',
									array_intersect(
										array_map(
											'serialize',
											$srl_list
										),
										array_map(
											'serialize',
											$srls
										)
									)
								));
							}
						}
					}
					// OR Search
					else
					{
						foreach ( $cond[$key] as $k => $v )
						{
							$args->var_value = $v;
							if ( in_array($val->type, array('radio', 'select')) )
							{
								$srls = executeQueryArray('addons.ap_extra_search.getDocumentListWithExtraVarsEqual', $args)->data;
							}
							else
							{
								$srls = executeQueryArray('addons.ap_extra_search.getDocumentListWithExtraVars', $args)->data;
							}

							// 결과값을 출력용 변수에 모두 병합
							$srl_list = array_merge($srl_list, $srls);

							// OR 검색에 맞게 중복 배열을 정리
							$srl_list = array_values(array_map(
								'unserialize',
								array_unique(
									array_map(
										'serialize',
										$srl_list
									)
								)
							));
						}
					}
				}
				// 확장변수 변수값이 그냥 문자열인 경우
				else
				{
					// 변수값을 서버에 저장. 이 값은 페이지 뒤로가기나 앞으로가기를 할 때 기억됨
					$val->setValue($cond[$key]);
					$args->var_value = $cond[$key];
					// 범위 검색 여부에 따라 별도 쿼리 실행
					if ( $addon_info->range_search === 'Y'
						&& in_array($val->eid, array_map('trim', explode(',', $addon_info->range_search_target)))
						&& (Context::get('extra_vars'.$val->idx) && Context::get('extra_vars'.$val->idx.'-2')) )
					{
						$args->var_eid = $val->eid;
						$args->var_start_value = Context::get('extra_vars'.$val->idx);
						$args->var_end_value = Context::get('extra_vars'.$val->idx.'-2');
						$srls = executeQueryArray('addons.ap_extra_search.getDocumentListWithinExtraVars', $args)->data;
					}
					else
					{
						if ( in_array($val->type, array('radio', 'select')) )
						{
							$srls = executeQueryArray('addons.ap_extra_search.getDocumentListWithExtraVarsEqual', $args)->data;
						}
						else
						{
							$srls = executeQueryArray('addons.ap_extra_search.getDocumentListWithExtraVars', $args)->data;
						}
					}

					// AND Search
					if ( $addon_info->type === 'and' )
					{
						// 출력할 문서 목록이 비어 있고 검색 세션에서 현재 변수가 처음일 경우, 검색 결과값 $srls를 출력용 문서 목록 $srl_list에 병합
						if ( $srl_list === array() && $search_session === false )
						{
							$srl_list = array_merge($srl_list, $srls);
							$search_session = true;
						}

						// AND 검색에 맞게 중복 배열을 정리
						$srl_list = array_values(array_map(
							'unserialize',
							array_intersect(
								array_map(
									'serialize',
									$srl_list
								),
								array_map(
									'serialize',
									$srls
								)
							)
						));
					}
					// OR Search
					else
					{
						// 결과값을 출력용 변수에 모두 병합
						$srl_list = array_merge($srl_list, $srls);

						// OR 검색에 맞게 중복 배열을 정리
						$srl_list = array_values(array_map(
							'unserialize',
							array_unique(
								array_map(
									'serialize',
									$srl_list
								)
							)
						));
					}
				}
			}
		}

		// 범위 검색 사용시 라이브러리 로드
		if ( $addon_info->range_search === 'Y' )
		{
			if ( $is_range_date === true )
			{
				Context::loadFile('./addons/ap_extra_search/js/moment.js');
				Context::loadFile('./addons/ap_extra_search/js/moment-with-locales.js');
				Context::loadFile('./addons/ap_extra_search/js/daterangepicker.js');
				Context::loadFile('./addons/ap_extra_search/js/date_range_search.js');
				Context::loadFile('./addons/ap_extra_search/js/daterangepicker.css');
			}
			if ( $is_range_data === true )
			{
				Context::loadFile('./common/js/plugins/ui/jquery-ui.min.js');
				Context::loadFile('./common/js/plugins/ui/jquery-ui.min.css');
				Context::loadFile('./addons/ap_extra_search/js/data_range_search.js');
			}
		}
	}

	// 애드온 변수를 스킨에 전달
	Context::set('extra_search', $addon_info);

	// 애드온이 게시판 서명검색까지도 다중검색에 포함하는 경우
	if ( $addon_info->signature === 'Y' )
	{
		if ( Context::get('search_signature') )
		{
			$search_signature = Context::get('search_signature');
			$args = new stdClass();
			$args->module_srl = $module_info->module_srl;
			$args->category_srl = Context::get('category');
			$member_list = executeQueryArray('addons.ap_extra_search.getMemberList', $args)->data;

			// AND Search
			if ( $addon_info->type === 'and' )
			{
				$_srl_list = array();
				foreach ($member_list as $member_info)
				{
					$sign_text = strip_tags(getModel('member')->getSignature($member_info->member_srl));
					if ( strpos($sign_text, $search_signature) !== false )
					{
						$args->member_srl = $member_info->member_srl;
						$srls = executeQueryArray('addons.ap_extra_search.getDocumentList', $args)->data;

						// 회원별로 서명검색 결과 누적
						$_srl_list = array_merge($_srl_list, $srls);
						$_srl_list = array_values(array_map(
							'unserialize',
							array_unique(
								array_map(
									'serialize',
									$_srl_list
								)
							)
						));
					}
				}

				// 출력할 문서 목록이 비어 있고 검색 세션에서 현재 변수가 처음일 경우, 별도의 문서 목록 $_srl_list를 출력용 문서 목록 $srl_list에 병합
				if ( $srl_list === array() && $search_session === false )
				{
					$srl_list = array_merge($srl_list, $_srl_list);
					$search_session = true;
				}

				// AND 검색에 맞게 중복 배열을 정리
				$srl_list = array_values(array_map(
					'unserialize',
					array_intersect(
						array_map(
							'serialize',
							$srl_list
						),
						array_map(
							'serialize',
							$_srl_list
						)
					)
				));
			}
			// OR Search
			else
			{
				foreach ($member_list as $member_info)
				{
					$sign_text = strip_tags(getModel('member')->getSignature($member_info->member_srl));
					if ( strpos($sign_text, $search_signature) !== false )
					{
						$args->member_srl = $member_info->member_srl;
						$srls = executeQueryArray('addons.ap_extra_search.getDocumentList', $args)->data;

						// 결과값을 출력용 변수에 모두 병합
						$srl_list = array_merge($srl_list, $srls);

						// OR 검색에 맞게 중복 배열을 정리
						$srl_list = array_values(array_map(
							'unserialize',
							array_unique(
								array_map(
									'serialize',
									$srl_list
								)
							)
						));
					}
				}
			}
		}
		$is_search = true;
		Context::set('search_signature', $search_signature);
	}

	// 주소창 파라미터의 확장변수 개수를 전역 변수로 설정, 그리고 검색 가능한 확장변수의 리스트를 따로 뽑아 전역 변수로 설정
	Context::set('is_search', $is_search);
	Context::set('extra_keys', $extra_keys);

	// 기본검색, 확장변수, 서명검색 등이 시도됐으면 결과값에 따라 문서 목록과 페이지 네비게이션 조정
	if ( count($cond) || ( Context::get('search_target') && Context::get('search_keyword') ) || Context::get('search_signature') )
	{
		// 그리고 취합된 검색결과가 있으면
		if ( count($srl_list) )
		{
			// 페이지 네비게이션 설정
			$total_count = count($srl_list);
			$total_page = ceil($total_count / $module_info->list_count);
			if ( $total_page < 1 )
			{
				$total_page = 1;
			}
			$page = Context::get('page') ? abs(Context::get('page')) : 1;
			if ( $page > $total_page )
			{
				$page = $total_page;
			}

			$page_navigation = new PageHandler($total_page, $total_page, $page, $module_info->page_count);
			Context::set('page_navigation', $page_navigation);

			// 문서 목록 설정 :: sort_index와 order_type 정리하고 이에 따라 문서 목록 재배열
			$order_target = Context::get('sort_index') ? Context::get('sort_index') : $module_info->order_target;
			$order_type = Context::get('order_type') ? Context::get('order_type') : $module_info->order_type;

			foreach ($srl_list as $key => $val)
			{
				if ( in_array($order_target, array('list_order', 'regdate', 'last_update', 'update_order', 'readed_count', 'voted_count', 'blamed_count', 'comment_count', 'trackback_count', 'uploaded_count', 'title', 'category_srl', 'nick_name', 'user_name', 'user_id')) )
				{
					$sort_key[$key] = $val->$order_target;
				}
				else
				{
					$extra_vars = $oDocumentModel->getExtraVars($module_info->module_srl, $val->document_srl);
					foreach ( $extra_vars as $v )
					{
						if ( $v->eid === $order_target )
						{
							$sort_key[$key] = $v->value;
							break;
						}
					}
				}
				$sort_key2[$key] = $key;
			}
			if ( is_array($sort_key) && count($sort_key) )
			{
				array_multisort($sort_key, $sort_key2, $srl_list);
			}
			if ( $order_type === 'desc' )
			{
				$srl_list = array_reverse($srl_list);
			}

			// 문서 목록 설정 :: 문서번호를 역순으로 넣고, 목록에 담길 문서 개수(list_count)에 따라 전체 문서목록을 나눠줌
			$srl_list = array_slice($srl_list, $module_info->list_count * ($page - 1), $module_info->list_count, true);
			$no = floor($total_count - ($module_info->list_count * ($page - 1)));
			foreach ( $srl_list as $l )
			{
				$document_list[$no] = $oDocumentModel->getDocument($l->document_srl);
				$no--;
			}
			Context::set('document_list', $document_list);
		}
		// 취합된 검색결과가 없으면
		else
		{
			// 페이지 네비게이션은 초기화
			$page_navigation = new PageHandler(1, 1, 1, $module_info->page_count);
			Context::set('page_navigation', $page_navigation);

			// 문서목록 비우기
			$document_list = array();
			Context::set('document_list', $document_list);
		}
	}
	// 검색이 시도되지 않았다면 애드온 실행 중지. 출력화면에는 정상적인 문서목록과 페이지 네비게이션이 나오게 됨
	else
	{
		return;
	}
}

if ( $called_position === 'before_display_content' && Context::getResponseMethod() === 'HTML' && Context::get('is_search') )
{
	// 검색창 삽입 지점 옵션 설정
	$addon_info->spot = $addon_info->spot ? $addon_info->spot : 'above';
	
	// 다국어 지원 언어팩 임포트
	Context::loadLang(_XE_PATH_ . 'addons/ap_extra_search/lang');

	// 스크립트로 php 변수 전달
	$_msg = Context::getLang('msg_reset');
	$_unit = preg_replace('/[^0-9]*/s', '', $addon_info->range_search_unit);
	if ( !$_unit || !is_numeric($_unit) || $_unit < 1 ) $_unit = 10;
	Context::addHtmlHeader("<script>
		var msg_reset = '$_msg',
			ap_extra_search_unit = $_unit;
	</script>");

	// 애드온 스킨 파일의 경로 확인
	$tpl_file = 'extra.html';
	$addon_info->skin = file_exists('./addons/ap_extra_search/skins/' . $addon_info->skin . '/' . $tpl_file) ? $addon_info->skin : 'sketchbook5';
	$tpl_path = './addons/ap_extra_search/skins/' . $addon_info->skin;

	// 애드온 스킨 파일을 컴파일
	$oTemplate = &TemplateHandler::getInstance();
	$tpl = $oTemplate->compile($tpl_path, $tpl_file);

	// 검색창 삽입 위치를 찾은 후, 삽입 지점에 따라 출력
	foreach ( array_map('trim', explode(',', $addon_info->position)) as $position )
	{
		$pattern = '/<(\w+)[^>]*class=("|\')[^"\']*\b' . $position . '\b[^"\']*\2[^>]*>.*?<\/\1>/is';
		if ( preg_match($pattern, $output) )
		{
			if ( $addon_info->spot === 'above' )
			{
				$output = preg_replace($pattern, $tpl . '$0', $output);
			}
			elseif ( $addon_info->spot === 'below' )
			{
				$output = preg_replace($pattern, '$0' . $tpl, $output);
			}
			break;
		}
	}
}