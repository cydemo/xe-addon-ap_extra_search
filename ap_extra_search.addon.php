<?php

/**
 * @file ap_extra_search.addon.php
 * @author cydemo <cydemo@gmail.com>
 */

if ( !defined('RX_VERSION') )
{
	return;
}

if ( $called_position === 'before_module_proc' && $this->module === 'board' && $this->act === 'dispBoardContent' )
{
	//  애드온 스킨의 삽입 위치가 지정되어 있지 않으면 중지
	if ( array_map('trim', explode(',', $addon_info->position))[0] === '' )
	{
		return;
	}

	// 검색 방식 설정
	$addon_info->type = $addon_info->type ? $addon_info->type : 'and';
	$addon_info->type_multi = $addon_info->type_multi ? $addon_info->type_multi : 'or';
	$addon_info->select2radio = $addon_info->select2radio ? $addon_info->select2radio : 'N';
	$addon_info->category = $addon_info->category ? $addon_info->category : 'N';
	$addon_info->basic = $addon_info->basic ? $addon_info->basic : 'N';
	$addon_info->signature = $addon_info->signature ? $addon_info->signature : 'N';
	$addon_info->range_search = $addon_info->range_search ? $addon_info->range_search : 'N';

	// 현재 모듈의 확장변수 사용자정의 호출
	$_extra_keys = DocumentModel::getExtraKeys($this->module_srl);

	// 사용 가능한 환경이 아니면 중지
	if ( count($_extra_keys) < 1 && $addon_info->category !== 'Y' && $addon_info->basic !== 'Y' && $addon_info->signature !== 'Y' )
	{
		return;
	}

	// 코어의 getDocumentPage 함수 중복 실행 방지를 위한 페이지 설정
	if ( Context::get('document_srl') && !Context::get('page') )
	{
		Context::set('page', 1);
	}

	getController('module')->addTriggerFunction('document.getDocumentList', 'before', function($obj) use($addon_info, $_extra_keys)
	{
		// 카테고리 복수 검색에 대비한 옵션 설정
		$obj->category_srl = Context::get('category_srls') ?: $obj->category_srl;

		// 목록 쿼리의 실행을 위한 매개변수 설정
		$args = new stdClass;
		$args->module_srl = $obj->module_srl ?? null;
		$args->exclude_module_srl = $obj->exclude_module_srl ?? null;
		$args->category_srl = $obj->category_srl ?? null;
		if (isset($obj->member_srl) && $obj->member_srl)
		{
			$args->member_srl = $obj->member_srl;
		}
		elseif (isset($obj->member_srls) && $obj->member_srls)
		{
			$args->member_srl = $obj->member_srls;
		}
		$args->order_type = (isset($obj->order_type) && $obj->order_type === 'desc') ? 'desc' : 'asc';
		$args->sort_index = $obj->sort_index;
		$args->page = $obj->page ?? 1;
		$args->list_count = $obj->list_count ?? 20;
		$args->page_count = $obj->page_count ?? 10;
		$args->start_date = $obj->start_date ?? null;
		$args->end_date = $obj->end_date ?? null;
		$args->start_regdate = $obj->start_regdate ?? null;
		$args->end_regdate = $obj->end_regdate ?? null;
		$args->s_is_notice = ($obj->except_notice ?? false) ? 'N' : null;
		$args->statusList = $obj->statusList ?? array(DocumentModel::getConfigStatus('public'), DocumentModel::getConfigStatus('secret'));
		$args->columnList = $obj->columnList ?? array();

		// get directly module_srl by mid
		if(isset($obj->mid) && $obj->mid)
		{
			$args->module_srl = ModuleModel::getModuleSrlByMid($obj->mid);
		}

		// add subcategories
		if ( isset($args->category_srl) && $args->category_srl )
		{
			$category_list = DocumentModel::getCategoryList($args->module_srl);
			if ( !is_array($args->category_srl) )
			{
				$args->category_srl = explode(',', preg_replace('/[^0-9\,]+/s', '', $args->category_srl));
			}
			foreach ( $args->category_srl as $category_srl )
			{
				if ( isset($category_list[$category_srl]) && !empty($category_list[$category_srl]->childs) )
				{
					$args->category_srl = array_merge($args->category_srl, $category_list[$category_srl]->childs);
				}
			}
		}

		// add default prefix
		if($args->sort_index && strpos($args->sort_index, '.') === false)
		{
			$args->sort_index = 'documents.' . $args->sort_index;
		}
		foreach($args->columnList as $key => $column)
		{
			if(strpos($column, '.') !== false)
			{
				continue;
			}
			$args->columnList[$key] = 'documents.' . $column;
		}


		// 문서 목록 작업을 위한 배열 변수 정의
		$cond = array();
		$extra_keys = array();
		$is_search = false;

		// 현재 모듈에 사용자정의된 확장변수가 있을 때만 실행
		if ( count($_extra_keys) )
		{
			$eids = [];
			$is_range_date = false;
			$is_range_data = false;

			// 확장변수 다중검색 기본 설정
			foreach ( $_extra_keys as $key => $val )
			{
				$eids[] = $val->eid;

				// 검색 불가 확장변수면 통과
				if ( $val->search !== 'Y' )
				{
					continue;
				}

				// 파라미터에 지정된 값이 있으면 $cond에 저장
				if ( Context::get('extra_vars' . $key) )
				{
					$cond[$key] = Context::get('extra_vars' . $key);
				}

				// 범위 검색 적용되는 변수 여부를 확인
				if ( $addon_info->range_search === 'Y' && in_array($val->eid, array_map('trim', explode(',', $addon_info->range_search_target))) )
				{
					if ( $val->type === 'date' )
					{
						$is_range_date = true;
					}
					else
					{
						$is_range_data = true;
						$args->var_eid = $val->eid;
						$max_min = executeQueryArray('addons.ap_extra_search.getMaxAndMinValueWithinExtraVars', $args)->data;
						$val->max = $max_min[0]->max;
						$val->min = $max_min[0]->min;
					}
				}

				$is_search = true;
				$extra_keys[$key] = $val;
			}

			// 범위 검색 사용시 라이브러리 로드
			if ( $addon_info->range_search === 'Y' )
			{
				if ( $is_range_date )
				{
					Context::loadFile('./addons/ap_extra_search/js/moment.js');
					Context::loadFile('./addons/ap_extra_search/js/moment-with-locales.js');
					Context::loadFile('./addons/ap_extra_search/js/daterangepicker.js');
					Context::loadFile('./addons/ap_extra_search/js/date_range_search.js');
					Context::loadFile('./addons/ap_extra_search/js/daterangepicker.css');
				}
				if ( $is_range_data )
				{
					Context::loadFile('./common/js/plugins/ui/jquery-ui.min.js');
					Context::loadFile('./common/js/plugins/ui/jquery-ui.min.css');
					Context::loadFile('./addons/ap_extra_search/js/data_range_search.js');
				}
			}

			// 애드온 변수를 스킨에 전달
			Context::set('extra_search', $addon_info);
		}


		// 0. 목록 쿼리를 위한 기본 작업
		$columns = implode(',', $this->columnList);
		$tables = 'documents';
		$conditions = 'documents.module_srl = ?';
		$cond_args = array($args->module_srl);
		if ( isset($args->category_srl) )
		{
			if ( !is_array($args->category_srl) )
			{
				$args->category_srl = array($args->category_srl);
			}
			$conditions .= ' AND documents.category_srl IN (';
			$conditions .= implode(',', array_fill(0, count($args->category_srl), '?'));
			$conditions .= ')';
			$cond_args = [...$cond_args, ...$args->category_srl];
		}


		// 1. 애드온이 게시판 기본검색까지도 다중검색에 포함하는 경우
		if ( $addon_info->basic === 'Y' )
		{
			// 기본검색의 검색대상에서 확장변수를 제거
			Context::set('search_option', array_intersect_key
				(
					Context::get('search_option'), array_flip($this->search_option)
				)
			);

			// default
			$search_target = $obj->search_target ?? null;
			$search_keyword = trim($obj->search_keyword ?? null) ?: null;

			// 검색 대상 및 검색어에 따라 쿼리 설정
			if ( $search_target && $search_keyword )
			{
				switch ( $search_target )
				{
					case 'title' :
					case 'content' :
					case 'comment' :
					case 'tag' :
					case 'title_content' :
						$use_division = true;
						$search_keyword = trim(utf8_normalize_spaces($search_keyword));
						if ( $search_target === 'title_content' )
						{
							$conditions .= ' AND (documents.title LIKE ? OR documents.content LIKE ?)';
							$cond_args[] = '%' . $search_keyword . '%';
							$cond_args[] = '%' . $search_keyword . '%';
						}
						elseif ( $search_target === 'comment' )
						{
							$conditions .= ' AND documents.document_srl';
							$conditions .= ' IN (SELECT document_srl FROM comments';
							$conditions .= ' WHERE documents.module_srl = comments.module_srl AND comments.content LIKE ?)';
							$cond_args[] = '%' . $search_keyword . '%';
						}
						elseif ( $search_target === 'tag' )
						{
							$conditions .= ' AND (documents.tags LIKE ?)';
							$cond_args[] = '%' . $search_keyword . '%';
						}
						else
						{
							$conditions .= ' AND (documents.' . $search_target . ' LIKE ?)';
							$cond_args[] = '%' . $search_keyword . '%';
						}
						break;
					case 'user_id' :
					case 'user_name' :
					case 'nick_name' :
					case 'email_address' :
					case 'homepage' :
					case 'regdate' :
					case 'last_update' :
					case 'ipaddress' :
						$conditions .= ' AND (documents.' . $search_target . ' LIKE ?)';
						$cond_args[] = '%' . str_replace(' ', '%', $search_keyword) . '%';
						break;
					case 'member_srl' :
						$conditions .= ' AND (documents.' . $search_target . ' = ?)';
						$cond_args[] = (int)$search_keyword;
						break;
					case 'readed_count' :
					case 'voted_count' :
					case 'comment_count' :
					case 'trackback_count' :
					case 'uploaded_count' :
						$conditions .= ' AND (documents.' . $search_target . ' >= ?)';
						$cond_args[] = (int)$search_keyword;
						break;
					case 'blamed_count' :
						$conditions .= ' AND (documents.' . $search_target . ' <= ?)';
						$cond_args[] = (int)$search_keyword * -1;
						break;
					case 'is_notice' :
						$conditions .= ' AND (documents.' . $search_target . ' = ?)';
						$cond_args[] = $search_keyword === 'Y' ? 'Y' : 'N';
						break;
					case 'is_secret' :
						$conditions .= ' AND (documents.' . $search_target . ' = ?)';
						if ( $search_keyword === 'N' )
						{
							$cond_args[] = array(DocumentModel::getConfigStatus('public'));
						}
						elseif ( $search_keyword === 'Y' )
						{
							$cond_args[] = array(DocumentModel::getConfigStatus('secret'));
						}
						elseif ( $search_keyword === 'temp' )
						{
							$cond_args[] = array(DocumentModel::getConfigStatus('temp'));
						}
						break;
					default :
						break;
				}

				// exclude secret documents in searching if current user does not have privilege
				if(!isset($args->member_srl) || !$args->member_srl || !Context::get('is_logged') || $args->member_srl !== Context::get('logged_info')->member_srl)
				{
					$module_info = ModuleModel::getModuleInfoByModuleSrl($args->module_srl);
					if(!ModuleModel::getGrant($module_info, Context::get('logged_info'))->manager)
					{
						$args->comment_is_secret = 'N';
						$args->statusList = array(DocumentModel::getConfigStatus('public'));
					}
				}

				$is_search = true;
			}
		}

		// 2. 확장변수가 쿼리스트링에 포함된 경우
		if ( !empty($cond) )
		{
			$i = 0;
			foreach ( $cond as $key => $val )
			{
				if ( !is_array($val) )
				{
					$val = array($val);
				}

				// 변수값을 서버에 저장. 이 값은 페이지 뒤로가기나 앞으로가기를 할 때 기억됨
				$extra_keys[$key]->setValue(implode('|@|', $val));

				if ( $i === 0 )
				{
					$conditions .= ' AND (documents.document_srl';
				}
				else
				{
					$conditions .= ( $addon_info->type === 'and' ) ? ' AND' : ' OR';
					$conditions .= ' documents.document_srl';
				}
				$conditions .= ' IN (SELECT document_srl FROM document_extra_vars WHERE';
				foreach ( $val as $k => $v )
				{
					if ( $k > 0 )
					{
						$conditions .= ( $addon_info->type_multi === 'or' && $extra_keys[$key]->type === 'checkbox' ) ? ' OR' : ' AND';
					}

					if ( $addon_info->range_search === 'Y'
						&& in_array($extra_keys[$key]->eid, array_map('trim', explode(',', $addon_info->range_search_target)))
						&& ($v && Context::get('extra_vars'.$key.'-2')) )
					{
						$_v = Context::get('extra_vars'.$key.'-2');
						$conditions .= ' (var_idx = ? AND value >= ? AND value <= ?)';
						$cond_args[] = $key;
						$cond_args[] = $v;
						$cond_args[] = $_v;
					}
					else
					{
						if ( in_array($extra_keys[$key]->type, array('radio', 'select')) )
						{
							$conditions .= ' (var_idx = ? AND value = ?)';
							$cond_args[] = $key;
							$cond_args[] = $v;
						}
						else
						{
							$conditions .= ' (var_idx = ? AND value LIKE ?)';
							$cond_args[] = $key;
							$cond_args[] = '%' . $v . '%';
						}
					}
				}
				$conditions .= ')';

				if ( $i === count($cond) - 1 )
				{
					$conditions .= ')';
				}
				$i++;
			}
		}

		// 3. 애드온이 게시판 서명검색까지도 다중검색에 포함하는 경우
		if ( $addon_info->signature === 'Y' && Context::get('search_signature') )
		{
			// https://stackoverflow.com/questions/24783862/list-all-the-files-and-folders-in-a-directory-with-php-recursive-function#answer-24784144
			function getMemberSrlsWithSignature($dir, &$results = array())
			{
				$sub_dir = scandir($dir);
				foreach ( $sub_dir as $key => $val )
				{
					$path = realpath($dir . DIRECTORY_SEPARATOR . $val);
					if ( !is_dir($path) )
					{
						preg_match('/(\d+)\.signature\.php/', $path, $matches);
						$results[] = $matches[1];
					}
					else if ( $val !== '.' && $val !== '..' )
					{
						getMemberSrlsWithSignature($path, $results);
					}
				}
				return $results;
			}

			$dir = RX_BASEDIR . 'files/member_extra_info/signature';
			$member_list_with_signature = getMemberSrlsWithSignature($dir);

			$search_signature = Context::get('search_signature');

			$member_srl_list = array();
			foreach ( $member_list_with_signature as $member_srl )
			{
				$sign_text = strip_tags(MemberModel::getSignature($member_srl));
				if ( strpos($sign_text, $search_signature) !== false )
				{
					$member_srl_list[] = $member_srl;
				}
			}

			if ( !empty($member_srl_list) )
			{
				$conditions .= ' AND documents.member_srl IN (';
				$conditions .= implode(',', array_fill(0, count($member_srl_list), '?'));
				$conditions .= ')';
				$cond_args = [...$cond_args, ...$member_srl_list];
			}

			$is_search = true;
		}

		// 문서 상태에 따라 목록 수집
		$conditions .= ' AND documents.status IN (';
		$conditions .= implode(',', array_fill(0, count($args->statusList), '?'));
		$conditions .= ')';
		$cond_args = [...$cond_args, ...$args->statusList];

		// 애드온의 다른 영역이나 스킨에서 사용할 변수 선언 및 전달
		Context::set('is_search', $is_search);
		Context::set('extra_keys', $extra_keys);

		// total_count 구하기
		if ( in_array($obj->sort_index, $eids) )
		{
			$_tables = $tables . ', document_extra_vars AS extra_sort';
			$conditions = $conditions . ' AND extra_sort.eid = ? AND extra_sort.lang_code = ? AND documents.document_srl = extra_sort.document_srl';
				$cond_args[] = $obj->sort_index;
				$cond_args[] = Context::getLangType();
			$query = 'SELECT DISTINCT COUNT(documents.document_srl) AS count FROM ' . $_tables . ' WHERE ' . $conditions;
		}
		else
		{
			$query = 'SELECT COUNT(document_srl) AS count FROM ' . $tables . ' WHERE ' . $conditions;
		}
		$oDB = DB::getInstance();
		$stmt = $oDB->query($query, $cond_args);
		$result = $stmt->fetchAll();
		$total_count = $result[0]->count;

		// set the current page of documents
		$document_srl = Context::get('document_srl');
		if ( $document_srl )
		{
			if ( $this->module_info->skip_bottom_list_for_robot === 'Y' && isCrawler() )
			{
				Context::set('page', $args->page = null);
			}
			else
			{
				$oDocument = DocumentModel::getDocument($document_srl);
				if ( $oDocument->isExists() && !$oDocument->isNotice() )
				{
					$days = $this->module_info->skip_bottom_list_days ?: 30;
					if ( $oDocument->getRegdateTime() < (time() - (86400 * $days)) && $this->module_info->skip_bottom_list_for_olddoc === 'Y' )
					{
						Context::set('page', $args->page = null);
					}
					else
					{
						if ( in_array($obj->sort_index, ['list_order', 'update_order', 'regdate']) )
						{
							if ( $obj->sort_index === 'regdate' )
							{
								if ( $obj->order_type === 'asc' )
								{
									$query .= ' AND (' . $obj->sort_index . ' >= ?)';
								}
								else
								{
									$query .= ' AND (' . $obj->sort_index . ' <= ?)';
								}
							}
							else
							{
								if ( $obj->order_type === 'desc' )
								{
									$query .= ' AND (' . $obj->sort_index . ' >= ?)';
								}
								else
								{
									$query .= ' AND (' . $obj->sort_index . ' <= ?)';
								}
							}
							$page_args = array_merge($cond_args, array($oDocument->get($obj->sort_index)));
							$oDB = DB::getInstance();
							$stmt = $oDB->query($query, $page_args);
							$result = $stmt->fetchAll();
							$_count = $result[0]->count;
							$args->page = (int)(($_count - 1) / $args->list_count) + 1;
							Context::set('page', $args->page);
						}
						else
						{
							Context::set('page', $args->page = 1);
						}
					}
				}
			}
		}

		// 페이지 네비게이션 설정
		$total_page = ceil($total_count / $args->list_count);
		if ( $total_page < 1 )
		{
			$total_page = 1;
		}
		$page = $args->page ?: 1;
		if ( $page > $total_page )
		{
			$page = $total_page;
		}
		$page_navigation = new PageHandler($total_count, $total_page, $page, $args->page_count);
		Context::set('page_navigation', $page_navigation);

		// 쿼리 실행
		if ( in_array($obj->sort_index, $eids) )
		{
			$columns = 'DISTINCT ' . $columns . ',extra_sort.value';
			$tables = $tables . ', document_extra_vars AS extra_vars, document_extra_vars AS extra_sort';
			$conditions .= ' AND documents.document_srl = extra_vars.document_srl AND extra_sort.eid = ? AND extra_sort.lang_code = ? AND documents.document_srl = extra_sort.document_srl';
				$cond_args[] = $obj->sort_index;
				$cond_args[] = Context::getLangType();
			$navigation = ' ORDER BY extra_sort.value ' . strtoupper($args->order_type) .' LIMIT ?, ?';
		}
		else
		{
			$navigation = ' ORDER BY ' . $args->sort_index . ' '. strtoupper($args->order_type) .' LIMIT ?, ?';
		}
		$cond_args[] = $args->list_count * ($page - 1);
		$cond_args[] = $args->list_count;

		$query = 'SELECT ' . $columns . ' FROM ' . $tables . ' WHERE ' . $conditions . $navigation;
		$oDB = DB::getInstance();
		$stmt = $oDB->query($query, $cond_args);
		$result = $stmt->fetchAll();

		// 쿼리 결과에 정렬 번호 지정
		$no = floor($total_count - ($args->list_count * ($page - 1)));
		$_result = array();
		foreach ( $result as $val )
		{
			$_result[$no] = $val;
			$no--;
		}

		// 문서 목록과 페이지네비게이션을 getDocumentList 함수로 전달
		$output = new BaseObject();
		$output->total_count = $total_count;
		$output->total_page = $total_page;
		$output->page = $page;
		$output->data = $_result;
		$output->page_navigation = $page_navigation;

		$obj->use_alternate_output = $output;
		unset($output);
		return $obj;
	});
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
	if ( !$_unit || !is_numeric($_unit) || $_unit < 1 )
	{
		$_unit = 10;
	}
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