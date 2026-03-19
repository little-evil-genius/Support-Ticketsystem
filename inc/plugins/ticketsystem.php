<?php
/**
 * Support-Ticketsystem - by little.evil.genius
 * https://github.com/little-evil-genius/Support-Ticketsystem
 * https://storming-gates.de/member.php?action=profile&uid=1712
*/

// Direktzugriff auf die Datei aus Sicherheitsgründen sperren
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// HOOKS
$plugins->add_hook("admin_rpgstuff_action_handler", "ticketsystem_admin_rpgstuff_action_handler");
$plugins->add_hook("admin_rpgstuff_permissions", "ticketsystem_admin_rpgstuff_permissions");
$plugins->add_hook("admin_rpgstuff_menu", "ticketsystem_admin_rpgstuff_menu");
$plugins->add_hook("admin_load", "ticketsystem_admin_manage");
$plugins->add_hook("admin_rpgstuff_update_stylesheet", "ticketsystem_admin_update_stylesheet");
$plugins->add_hook("admin_rpgstuff_update_plugin", "ticketsystem_admin_update_plugin");
$plugins->add_hook("newthread_start", "ticketsystem_newthread_start");
$plugins->add_hook("datahandler_post_validate_thread", "ticketsystem_validate_newthread");
$plugins->add_hook("newthread_do_newthread_end", "ticketsystem_do_newthread");
$plugins->add_hook("global_intermediate", "ticketsystem_banner");
$plugins->add_hook("misc_start", "ticketsystem_misc");
$plugins->add_hook('forumdisplay_thread_end', 'ticketsystem_forumdisplay');
$plugins->add_hook('showthread_start', 'ticketsystem_showthread');
$plugins->add_hook('global_start', 'ticketsystem_register_myalerts_formatter_back_compat'); // Backwards-compatible alert formatter registration hook-ins.
$plugins->add_hook('xmlhttp', 'ticketsystem_register_myalerts_formatter_back_compat', -2/* Prioritised one higher (more negative) than the MyAlerts hook into xmlhttp */);
$plugins->add_hook('myalerts_register_client_alert_formatters', 'ticketsystem_register_myalerts_formatter'); // Backwards-compatible alert formatter registration hook-ins.
 
// Die Informationen, die im Pluginmanager angezeigt werden
function ticketsystem_info()
{
	return array(
		"name"		=> "Support-Ticketsystem",
		"description"	=> "Erweitert das Forum um ein Support-Ticketsystem für Teammitglieder.",
		"website"	=> "https://github.com/little-evil-genius/Support-Ticketsystem",
		"author"	=> "little.evil.genius",
		"authorsite"	=> "https://storming-gates.de/member.php?action=profile&uid=1712",
		"version"	=> "1.0",
		"compatibility" => "18*"
	);
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin installiert wird (optional).
function ticketsystem_install() {
    
    global $db, $lang;

    // SPRACHDATEI
    $lang->load("ticketsystem");

    // RPG Stuff Modul muss vorhanden sein
    if (!file_exists(MYBB_ADMIN_DIR."/modules/rpgstuff/module_meta.php")) {
		flash_message($lang->ticketsystem_error_rpgstuff, 'error');
		admin_redirect('index.php?module=config-plugins');
	}

    // Accountswitcher muss vorhanden sein
    if (!function_exists('accountswitcher_is_installed')) {
		flash_message($lang->ticketsystem_error_accountswitcher, 'error');
		admin_redirect('index.php?module=config-plugins');
	}

    // DATENBANKTABELL & FELDER
    ticketsystem_database();

    // EINSTELLUNGEN HINZUFÜGEN
    $maxdisporder = $db->fetch_field($db->query("SELECT MAX(disporder) FROM ".TABLE_PREFIX."settinggroups"), "MAX(disporder)");
    $setting_group = array(
        'name'          => 'ticketsystem',
        'title'         => 'Support-Ticketsystem',
        'description'   => 'Einstellungen für das Support-Ticketsystem',
        'disporder'     => $maxdisporder+1,
        'isdefault'     => 0
    );
    $db->insert_query("settinggroups", $setting_group);

    // Einstellungen
    ticketsystem_settings();
    rebuild_settings();

    // TEMPLATES ERSTELLEN
	// Template Gruppe für jedes Design erstellen
    $templategroup = array(
        "prefix" => "ticketsystem",
        "title" => $db->escape_string("Support-Ticketsystem"),
    );
    $db->insert_query("templategroups", $templategroup);
    // Templates 
    ticketsystem_templates();
    
    // STYLESHEET HINZUFÜGEN
	require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
    $css = ticketsystem_stylesheet();
    $sid = $db->insert_query("themestylesheets", $css);
	$db->update_query("themestylesheets", array("cachefile" => "ticketsystem.css"), "sid = '".$sid."'", 1);

	$tids = $db->simple_select("themes", "tid");
	while($theme = $db->fetch_array($tids)) {
		update_theme_stylesheet_list($theme['tid']);
	}
}
 
// Funktion zur Überprüfung des Installationsstatus; liefert true zurürck, wenn Plugin installiert, sonst false (optional).
function ticketsystem_is_installed() {

    global $db;
    
    if ($db->table_exists("ticketsystem")) {
        return true;
    }
    return false;
} 
 
// Diese Funktion wird aufgerufen, wenn das Plugin deinstalliert wird (optional).
function ticketsystem_uninstall() {

    global $db;

    // DATENBANKTABELLE LÖSCHEN
    if($db->table_exists("ticketsystem")) {
        $db->drop_table("ticketsystem");
    }

    // DATENBANKFELDER LÖSCHEN
    if ($db->field_exists("ticketsystem_prefix", "threads")) {
		$db->drop_column("threads", "ticketsystem_prefix");
	}
    if ($db->field_exists("ticketsystem_teammember", "threads")) {
		$db->drop_column("threads", "ticketsystem_teammember");
	}
    if ($db->field_exists("ticketsystem_solved", "threads")) {
		$db->drop_column("threads", "ticketsystem_solved");
	}
    
    // EINSTELLUNGEN LÖSCHEN
    $db->delete_query('settings', "name LIKE 'ticketsystem%'");
    $db->delete_query('settinggroups', "name = 'ticketsystem'");
    rebuild_settings();

    // TEMPLATGRUPPE LÖSCHEN
    $db->delete_query("templategroups", "prefix = 'ticketsystem'");

    // TEMPLATES LÖSCHEN
    $db->delete_query("templates", "title LIKE 'ticketsystem%'");

    // STYLESHEET ENTFERNEN
	require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
	$db->delete_query("themestylesheets", "name = 'ticketsystem.css'");
	$query = $db->simple_select("themes", "tid");
	while($theme = $db->fetch_array($query)) {
		update_theme_stylesheet_list($theme['tid']);
	}
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin aktiviert wird.
function ticketsystem_activate() {

    global $db, $cache;

    if(class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
		$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

		if (!$alertTypeManager) {
			$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
		}

		$alertType = new MybbStuff_MyAlerts_Entity_AlertType();
		$alertType->setCode('ticketsystem_alert'); // The codename for your alert type. Can be any unique string.
		$alertType->setEnabled(true);
		$alertType->setCanBeUserDisabled(true);

		$alertTypeManager->add($alertType);
    }

    // VARIABLEN EINFÜGEN
    require MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets('showthread', '#'.preg_quote('{$newreply}').'#', '{$newreply} {$ticketsystem_button}');
	find_replace_templatesets('showthread', '#'.preg_quote('{$thread[\'subject\']}').'#', '{$ticketsystem_prefix}{$thread[\'subject\']}{$ticketsystem_teammember}');
	find_replace_templatesets('forumdisplay_thread', '#'.preg_quote('{$thread[\'threadprefix\']}').'#', '{$ticketsystem_prefix}{$thread[\'threadprefix\']}');
	find_replace_templatesets('forumdisplay_thread', '#'.preg_quote('{$thread[\'profilelink\']}').'#', '{$ticketsystem_teammember}{$thread[\'profilelink\']}');
	find_replace_templatesets('header', '#'.preg_quote('{$pm_notice}').'#', '{$ticketsystem_banner}{$pm_notice}');
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin deaktiviert wird.
function ticketsystem_deactivate() {

    global $db, $cache;

    if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
		$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

		if (!$alertTypeManager) {
			$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
		}

		$alertTypeManager->deleteByCode('ticketsystem_alert');
	}
    
    // VARIABLEN ENTFERNEN
    require MYBB_ROOT."/inc/adminfunctions_templates.php";
    find_replace_templatesets("showthread", "#".preg_quote('{$ticketsystem_button}')."#i", '', 0);
    find_replace_templatesets("showthread", "#".preg_quote('{$ticketsystem_prefix}')."#i", '', 0);
    find_replace_templatesets("showthread", "#".preg_quote('{$ticketsystem_teammember}')."#i", '', 0);
    find_replace_templatesets("forumdisplay_thread", "#".preg_quote('{$ticketsystem_prefix}')."#i", '', 0);
    find_replace_templatesets("forumdisplay_thread", "#".preg_quote('{$ticketsystem_teammember}')."#i", '', 0);
    find_replace_templatesets("header", "#".preg_quote('{$ticketsystem_banner}')."#i", '', 0);
}

######################
### HOOK FUNCTIONS ###
######################

// ADMIN BEREICH - KONFIGURATION //

// action handler fürs acp konfigurieren
function ticketsystem_admin_rpgstuff_action_handler(&$actions) {
	$actions['ticketsystem'] = array('active' => 'ticketsystem', 'file' => 'ticketsystem');
}

// Benutzergruppen-Berechtigungen im ACP
function ticketsystem_admin_rpgstuff_permissions(&$admin_permissions) {

	global $lang;
	
    $lang->load('ticketsystem');

	$admin_permissions['ticketsystem'] = $lang->ticketsystem_permission;

	return $admin_permissions;
}

// im Menü einfügen
function ticketsystem_admin_rpgstuff_menu(&$sub_menu) {

    global $lang;

    $lang->load('ticketsystem');

    $sub_menu[] = [
        'id'    => 'ticketsystem',
        'title' => $lang->ticketsystem_nav,
        'link'  => 'index.php?module=rpgstuff-ticketsystem'
    ];
}

// die Verwaltung
function ticketsystem_admin_manage() {

    global $mybb, $db, $lang, $page, $run_module, $action_file;

    if ($page->active_action != 'ticketsystem') {
		return false;
	}

	$lang->load('ticketsystem');

	if ($run_module == 'rpgstuff' && $action_file == 'ticketsystem') {

        // Alle Teamies
        $all_teammembers = ticketsystem_teammember();

		// Add to page navigation
		$page->add_breadcrumb_item($lang->ticketsystem_breadcrumb_main, "index.php?module=rpgstuff-ticketsystem");

        // ÜBERSICHT
		if ($mybb->get_input('action') == "" || !$mybb->get_input('action')) {

            $page->output_header($lang->ticketsystem_overview_header);

			// Menü
			$sub_tabs['overview'] = [
				"title" => $lang->ticketsystem_tabs_overview,
				"link" => "index.php?module=rpgstuff-ticketsystem",
				"description" => $lang->ticketsystem_tabs_overview_desc
			];
            $sub_tabs['add'] = [
				"title" => $lang->ticketsystem_tabs_add,
				"link" => "index.php?module=rpgstuff-ticketsystem&amp;action=add"
			];
            $page->output_nav_tabs($sub_tabs, 'overview');

			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
			}

            // Übersichtsseite
            $form_container = new FormContainer($lang->ticketsystem_overview_container);
            $form_container->output_row_header($lang->ticketsystem_overview_container_prefix, array('style' => 'text-align: left;'));
            $form_container->output_row_header($lang->ticketsystem_overview_container_teammember, array('style' => 'text-align: center; width: 25%;'));
            $form_container->output_row_header($lang->ticketsystem_options_container, array('style' => 'text-align: center; width: 10%;'));
			
            $query_prefix = $db->query("SELECT * FROM ".TABLE_PREFIX."ticketsystem
            ORDER BY title ASC
            ");

            while ($prefix = $db->fetch_array($query_prefix)) {

                // Leer laufen lassen
                $tsid = "";
                $title = "";

                // Mit Infos füllen
                $tsid = $prefix['tsid'];
                $title = $prefix['title'];
                $form_container->output_cell('<strong><a href="index.php?module=rpgstuff-ticketsystem&amp;action=edit&amp;tsid='.$tsid.'">'.$title.'</a></strong>');   
                
                // Alle
                if ($prefix['teammembers'] == 0) {
                    $form_container->output_cell($lang->ticketsystem_overview_teammember_all);
                } 
                // einzelne 
                else {
                    $teammembers = [];
                    $teammembersArray = array_map('intval', explode(',', $prefix['teammembers']));
                    foreach ($teammembersArray as $uid) {
                        $playername = ticketsystem_playername($uid);    
                        $teammembers[] = $playername;
                    }
                
                    $form_container->output_cell(implode('<br>', $teammembers));   
                }

                // OPTIONEN
				$popup = new PopupMenu("ticketsystem_".$tsid, $lang->ticketsystem_options_popup);	
                $popup->add_item(
                    $lang->ticketsystem_options_popup_edit,
                    "index.php?module=rpgstuff-ticketsystem&amp;action=edit&amp;tsid=".$tsid
                );
                $popup->add_item(
                    $lang->ticketsystem_options_popup_delete,
                    "index.php?module=rpgstuff-ticketsystem&amp;action=delete&amp;tsid=".$tsid."&amp;my_post_key={$mybb->post_code}", 
					"return AdminCP.deleteConfirmation(this, '".$lang->ticketsystem_delete_notice."')"
                );
                $form_container->output_cell($popup->fetch(), array("class" => "align_center"));
                $form_container->construct_row();
            }

            if($db->num_rows($query_prefix) == 0){
                $form_container->output_cell($lang->ticketsystem_overview_noElements, array("colspan" => 3, 'style' => 'text-align: center;'));
                $form_container->construct_row();
			}
            
            $form_container->end();
            $page->output_footer();
			exit;
        }

        // HINZUFÜGEN
        if ($mybb->get_input('action') == "add") {
            
            if ($mybb->request_method == "post") {

                $errors = ticketsystem_validate_prefix();

                // No errors - insert
                if (empty($errors)) {

                    $teammembers_input = $mybb->get_input('teammembers', MyBB::INPUT_ARRAY);
                    $selected_teammember = [];
                    // Alle
                    if (array_key_exists('0', $teammembers_input)) {
                        $selected_teammember = [0];
                    } else {
                        // einzelne User
                        foreach ($teammembers_input as $uid => $value) {
                            if (is_numeric($uid)) {
                                $selected_teammember[] = (int)$uid;
                            }    
                        }
                    }
                    $inputs_teammember = implode(',', $selected_teammember);

                    $insert_prefix = array(
                        "title" => $db->escape_string($mybb->get_input('title')),
                        "displaystyle" => $db->escape_string($mybb->get_input('displaystyle')),
                        "teammembers" => $inputs_teammember
                    );
                    $db->insert_query("ticketsystem", $insert_prefix);

                    flash_message($lang->ticketsystem_add_flash, 'success');
                    admin_redirect("index.php?module=rpgstuff-ticketsystem");
                }
            }

            $page->add_breadcrumb_item($lang->ticketsystem_breadcrumb_add);
			$page->output_header($lang->ticketsystem_breadcrumb_main." - ".$lang->ticketsystem_add_header);

			// Menü
			$sub_tabs['overview'] = [
				"title" => $lang->ticketsystem_tabs_overview,
				"link" => "index.php?module=rpgstuff-ticketsystem"
			];
            $sub_tabs['add'] = [
				"title" => $lang->ticketsystem_tabs_add,
				"link" => "index.php?module=rpgstuff-ticketsystem&amp;action=add",
				"description" => $lang->ticketsystem_tabs_add_desc
			];
            $page->output_nav_tabs($sub_tabs, 'add');

			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
                $InputMember_values = $mybb->get_input('teammembers', MyBB::INPUT_ARRAY);
			} else {
                $inputs_teammember = "";
                $InputMember_values = array_filter(explode(",", $inputs_teammember));
            }

            // Build the form
            $form = new Form("index.php?module=rpgstuff-ticketsystem&amp;action=add", "post", "", 1);
            $form_container = new FormContainer($lang->ticketsystem_add_container);
            echo $form->generate_hidden_field("my_post_key", $mybb->post_code);

            $form_container->output_row(
                $lang->ticketsystem_form_title,
                $lang->ticketsystem_form_title_desc,
                $form->generate_text_box('title', $mybb->get_input('title'))
            );

            $form_container->output_row(
                $lang->ticketsystem_form_displaystyle,
                $lang->ticketsystem_form_displaystyle_desc,
                $form->generate_text_box('displaystyle', $mybb->get_input('displaystyle'))
            );

            $teammember_options = [];
            $all_selected = in_array((string)0, $InputMember_values, true);
            $teammember_options[] = $form->generate_check_box("teammembers[0]", 0, $lang->ticketsystem_form_teammember_all, ['id' => 'check_all_teammembers', 'checked' => $all_selected]);
            foreach ($all_teammembers as $uid => $name) {
                $checked = in_array((string)$uid, $InputMember_values, true);
                $teammember_options[] = $form->generate_check_box("teammembers[".$uid."]", $uid, $name, ['checked' => $checked, 'id' => "tm_".$uid]);
            }
            
            $form_container->output_row(
                $lang->ticketsystem_form_teammember,
                $lang->ticketsystem_form_teammember_desc,
                implode('<br />', $teammember_options),
                '',
                [],
                ['id' => 'row_teammember_options']
            );

            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->ticketsystem_add_button);
            $form->output_submit_wrapper($buttons);
            $form->end();
            $page->output_footer();
            exit;
        }

        // BEARBEITEN
        if ($mybb->get_input('action') == "edit") {

            // Get the data
            $tsid = $mybb->get_input('tsid', MyBB::INPUT_INT);
            $prefix_query = $db->simple_select("ticketsystem", "*", "tsid = '".$tsid."'");
            $prefix = $db->fetch_array($prefix_query);
            
            if ($mybb->request_method == "post") {
                    
                $tsid = $mybb->get_input('tsid', MyBB::INPUT_INT);

                $errors = ticketsystem_validate_prefix();

                // No errors - insert
                if (empty($errors)) {

                    $teammembers_input = $mybb->get_input('teammembers', MyBB::INPUT_ARRAY);
                    $selected_teammember = [];
                    // Alle
                    if (array_key_exists('0', $teammembers_input)) {
                        $selected_teammember = [0];
                    } else {
                        // einzelne User
                        foreach ($teammembers_input as $uid => $value) {
                            if (is_numeric($uid)) {
                                $selected_teammember[] = (int)$uid;
                            }    
                        }
                    }
                    $inputs_teammember = implode(',', $selected_teammember);

                    $update_prefix = array(
                        "title" => $db->escape_string($mybb->get_input('title')),
                        "displaystyle" => $db->escape_string($mybb->get_input('displaystyle')),
                        "teammembers" => $inputs_teammember
                    );
                    $db->update_query("ticketsystem", $update_prefix, "tsid='".$tsid."'");

                    flash_message($lang->ticketsystem_edit_flash, 'success');
                    admin_redirect("index.php?module=rpgstuff-ticketsystem");
                }
            }

            $page->add_breadcrumb_item($lang->ticketsystem_breadcrumb_edit);
			$page->output_header($lang->ticketsystem_breadcrumb_main." - ".$lang->ticketsystem_edit_header);

			// Menü
			$sub_tabs['overview'] = [
				"title" => $lang->ticketsystem_tabs_overview,
				"link" => "index.php?module=rpgstuff-ticketsystem"
			];
            $sub_tabs['edit'] = [
				"title" => $lang->ticketsystem_tabs_edit,
				"link" => "index.php?module=rpgstuff-ticketsystem&amp;action=edit",
				"description" => $lang->ticketsystem_tabs_edit_desc
			];
            $page->output_nav_tabs($sub_tabs, 'edit');

			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
				$title = $mybb->get_input('title');
				$displaystyle = $mybb->get_input('displaystyle');
                $InputMember_values = $mybb->get_input('teammembers', MyBB::INPUT_ARRAY);
			} else {
				$title = $prefix['title'];
				$displaystyle = $prefix['displaystyle'];
                $inputs_teammember = $prefix['teammembers'];
                $InputMember_values = array_filter(explode(",", $inputs_teammember));
            }

            // Build the form
            $form = new Form("index.php?module=rpgstuff-ticketsystem&amp;action=edit", "post", "", 1);
            $form_container = new FormContainer($lang->sprintf($lang->ticketsystem_edit_container, $prefix['title']));
            echo $form->generate_hidden_field("my_post_key", $mybb->post_code);
            echo $form->generate_hidden_field("tsid", $tsid);

            $form_container->output_row(
                $lang->ticketsystem_form_title,
                $lang->ticketsystem_form_title_desc,
                $form->generate_text_box('title', $title)
            );

            $form_container->output_row(
                $lang->ticketsystem_form_displaystyle,
                $lang->ticketsystem_form_displaystyle_desc,
                $form->generate_text_box('displaystyle', $displaystyle)
            );

            $teammember_options = [];
            $all_selected = in_array((string)0, $InputMember_values, true);
            $teammember_options[] = $form->generate_check_box("teammembers[0]", 0, $lang->ticketsystem_form_teammember_all, ['id' => 'check_all_teammembers', 'checked' => $all_selected]);
            foreach ($all_teammembers as $uid => $name) {
                $checked = in_array((string)$uid, $InputMember_values, true);
                $teammember_options[] = $form->generate_check_box("teammembers[".$uid."]", $uid, $name, ['checked' => $checked, 'id' => "tm_".$uid]);
            }
            
            $form_container->output_row(
                $lang->ticketsystem_form_teammember,
                $lang->ticketsystem_form_teammember_desc,
                implode('<br />', $teammember_options),
                '',
                [],
                ['id' => 'row_teammember_options']
            );

            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->ticketsystem_edit_button);
            $form->output_submit_wrapper($buttons);
            $form->end();
            $page->output_footer();
            exit;
        }

        // LÖSCHEN
        if ($mybb->get_input('action') == "delete") {
            
            // Get the data
            $tsid = $mybb->get_input('tsid', MyBB::INPUT_INT);

			// Error Handling
			if (empty($tsid)) {
				flash_message($lang->ticketsystem_error_invalid, 'error');
				admin_redirect("index.php?module=rpgstuff-ticketsystem");
			}

			// Cancel button pressed?
			if (isset($mybb->input['no']) && $mybb->input['no']) {
				admin_redirect("index.php?module=rpgstuff-ticketsystem");
			}

			if ($mybb->request_method == "post") {

                $db->delete_query('ticketsystem', "tsid = ".$tsid);

				flash_message($lang->ticketsystem_delete_flash, 'success');
				admin_redirect("index.php?module=rpgstuff-ticketsystem");
			} else {
				$page->output_confirm_action(
					"index.php?module=rpgstuff-ticketsystem&amp;action=delete&amp;tsid=".$tsid,
					$lang->ticketsystem_delete_notice
				);
			}
			exit;
        }
    }
}

// Stylesheet zum Master Style hinzufügen
function ticketsystem_admin_update_stylesheet(&$table) {

    global $db, $mybb, $lang;
	
    $lang->load('rpgstuff_stylesheet_updates');

    require_once MYBB_ADMIN_DIR."inc/functions_themes.php";

    // HINZUFÜGEN
    if ($mybb->input['action'] == 'add_master' AND $mybb->get_input('plugin') == "ticketsystem") {

        $css = ticketsystem_stylesheet();
        
        $sid = $db->insert_query("themestylesheets", $css);
        $db->update_query("themestylesheets", array("cachefile" => "ticketsystem.css"), "sid = '".$sid."'", 1);
    
        $tids = $db->simple_select("themes", "tid");
        while($theme = $db->fetch_array($tids)) {
            update_theme_stylesheet_list($theme['tid']);
        } 

        flash_message($lang->stylesheets_flash, "success");
        admin_redirect("index.php?module=rpgstuff-stylesheet_updates");
    }

    // Zelle mit dem Namen des Themes
    $table->construct_cell("<b>".htmlspecialchars_uni("Support-Ticketsystem")."</b>", array('width' => '70%'));

    // Ob im Master Style vorhanden
    $master_check = $db->fetch_field($db->query("SELECT tid FROM ".TABLE_PREFIX."themestylesheets 
    WHERE name = 'ticketsystem.css' 
    AND tid = 1
    "), "tid");
    
    if (!empty($master_check)) {
        $masterstyle = true;
    } else {
        $masterstyle = false;
    }

    if (!empty($masterstyle)) {
        $table->construct_cell($lang->stylesheets_masterstyle, array('class' => 'align_center'));
    } else {
        $table->construct_cell("<a href=\"index.php?module=rpgstuff-stylesheet_updates&action=add_master&plugin=ticketsystem\">".$lang->stylesheets_add."</a>", array('class' => 'align_center'));
    }
    
    $table->construct_row();
}

// Plugin Update
function ticketsystem_admin_update_plugin(&$table) {

    global $db, $mybb, $lang;
	
    $lang->load('rpgstuff_plugin_updates');

    // UPDATE
    if ($mybb->input['action'] == 'add_update' AND $mybb->get_input('plugin') == "ticketsystem") {

        // Templates 
        ticketsystem_templates('update');

        // Stylesheet
        $update_data = ticketsystem_stylesheet_update();
        $update_stylesheet = $update_data['stylesheet'];
        $update_string = $update_data['update_string'];
        if (!empty($update_string)) {

            // Ob im Master Style die Überprüfung vorhanden ist
            $masterstylesheet = $db->fetch_field($db->query("SELECT stylesheet FROM ".TABLE_PREFIX."themestylesheets WHERE tid = 1 AND name = 'ticketsystem.css'"), "stylesheet");
            $masterstylesheet = (string)($masterstylesheet ?? '');
            $update_string = (string)($update_string ?? '');
            $pos = strpos($masterstylesheet, $update_string);
            if ($pos === false) { // nicht vorhanden 
            
                $theme_query = $db->simple_select('themes', 'tid, name');
                while ($theme = $db->fetch_array($theme_query)) {
        
                    $stylesheet_query = $db->simple_select("themestylesheets", "*", "name='".$db->escape_string('ticketsystem.css')."' AND tid = ".$theme['tid']);
                    $stylesheet = $db->fetch_array($stylesheet_query);
        
                    if ($stylesheet) {

                        require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
        
                        $sid = $stylesheet['sid'];
            
                        $updated_stylesheet = array(
                            "cachefile" => $db->escape_string($stylesheet['name']),
                            "stylesheet" => $db->escape_string($stylesheet['stylesheet']."\n\n".$update_stylesheet),
                            "lastmodified" => TIME_NOW
                        );
            
                        $db->update_query("themestylesheets", $updated_stylesheet, "sid='".$sid."'");
            
                        if(!cache_stylesheet($theme['tid'], $stylesheet['name'], $updated_stylesheet['stylesheet'])) {
                            $db->update_query("themestylesheets", array('cachefile' => "css.php?stylesheet=".$sid), "sid='".$sid."'", 1);
                        }
            
                        update_theme_stylesheet_list($theme['tid']);
                    }
                }
            } 
        }

        // Datenbanktabellen & Felder
        ticketsystem_database();

        flash_message($lang->plugins_flash, "success");
        admin_redirect("index.php?module=rpgstuff-plugin_updates");
    }

    // Zelle mit dem Namen des Themes
    $table->construct_cell("<b>".htmlspecialchars_uni("Support-Ticketsystem")."</b>", array('width' => '70%'));

    // Überprüfen, ob Update erledigt
    $update_check = ticketsystem_is_updated();

    if (!empty($update_check)) {
        $table->construct_cell($lang->plugins_actual, array('class' => 'align_center'));
    } else {
        $table->construct_cell("<a href=\"index.php?module=rpgstuff-plugin_updates&action=add_update&plugin=ticketsystem\">".$lang->plugins_update."</a>", array('class' => 'align_center'));
    }
    
    $table->construct_row();
}

// FORUM //

// Dropbox für das Support-Thema
function ticketsystem_newthread_start() {

    global $templates, $mybb, $lang, $fid, $db, $post_errors, $newthread_ticketsystem, $prefix, $prefix_options;

    $supportarea = $mybb->settings['ticketsystem_supportarea'];

    // zurück, wenn es nicht der Supportbereich ist
    if ($fid != $supportarea) {
        $newthread_ticketsystem = ""; 
        return;
    }

	$allprefix_query = $db->query("SELECT tsid, title FROM ".TABLE_PREFIX."ticketsystem ORDER BY title ASC");
    $all_prefix = [];
    while($allperfix = $db->fetch_array($allprefix_query)) {
        $tsid = $allperfix['tsid'];
        $all_prefix[$tsid] = $allperfix['title'];
    }

	// Sprachdatei laden
    $lang->load('ticketsystem');

	// previewing new thread?
	if(isset($mybb->input['previewpost']) || $post_errors) {
		$prefix = htmlspecialchars_uni($mybb->get_input('ticketsystem'));
		if (!empty($prefix)) {
			$prefix_options = ""; 
		} else {
			$prefix_options = "<option value=\"\">".$lang->ticketsystem_select."</option>";	
		}
    } else {
		$prefix_options = "<option value=\"\">".$lang->ticketsystem_select."</option>";	
	}

	foreach ($all_prefix as $tsid => $title) {
		if (!$mybb->get_input('ticketsystem') AND $mybb->get_input('ticketsystem') == $tsid) {
			$selected = "selected";
		} else {
			$selected = "";
		}
		$prefix_options .= "<option value='".$tsid."' ".$selected.">".$title."</option>";
	}

	eval("\$newthread_ticketsystem = \"".$templates->get("ticketsystem_newthread")."\";");
}

// Überprüfen, ob ein Support-Thema ausgewählt wurde
function ticketsystem_validate_newthread(&$dh) {

	global $mybb, $lang, $fid;

    if (is_member($mybb->settings['ticketsystem_team'])) return;

    $lang->load('ticketsystem');

    $supportarea = $mybb->settings['ticketsystem_supportarea'];

    if ($fid == $supportarea) {
        if (empty($mybb->get_input('ticketsystem'))) {
			$dh->set_error($lang->ticketsystem_error);
        }
	}
}

// Support-Thema speichern bei er Eröffnung
function ticketsystem_do_newthread() {

    global $mybb, $db, $fid, $tid;

    $supportarea = $mybb->settings['ticketsystem_supportarea'];

    if ($fid != $supportarea) return;

	$new_ticket = array(
		"ticketsystem_prefix" => $mybb->get_input('ticketsystem')
	);
	$db->update_query("threads", $new_ticket, "tid = ".$tid);
}

// Teammeldungen entsprechend der Aufgaben
function ticketsystem_banner() {

	global $db, $mybb, $lang, $templates, $ticketsystem_banner;

    if (!is_member($mybb->settings['ticketsystem_team'])) {
        $ticketsystem_banner = "";
        return;
    }
	
    $lang->load('ticketsystem');

	$activeUID = $mybb->user['uid'];
    $allUIDs = implode(',', array_keys(ticketsystem_get_allchars($activeUID)));
    $supportarea = $mybb->settings['ticketsystem_supportarea'];

    $openSupport_query = $db->query("SELECT tid, firstpost, uid, username, ticketsystem_prefix, ticketsystem_teammember FROM ".TABLE_PREFIX."threads
    WHERE fid = ".$supportarea."
    AND ticketsystem_solved = 0
    AND (ticketsystem_teammember = 0 OR ticketsystem_teammember IN (".$allUIDs."))
    AND ticketsystem_prefix IN (SELECT tsid FROM ".TABLE_PREFIX."ticketsystem WHERE teammembers = 0 OR CONCAT(',', teammembers, ',') LIKE '%,".$activeUID.",%')
    ");
    
    $ticketsystem_banner = "";
    while($support = $db->fetch_array($openSupport_query)) {

        // Leer laufen lassen
        $tid = "";
        $pid = "";
        $threadUID = "";
        $threadUsername = "";
        $ticketsystem_prefix = "";
        $ticketsystem_teammember = "";

        $username = "";
        $prefixTitle = "";
        $bannertext = "";
        $option = "";

        // Mit Infos füllen
        $tid = $support['tid'];
        $pid = $support['firstpost'];
        $threadUID = $support['uid'];
        $threadUsername = $support['username'];
        $ticketsystem_prefix = $support['ticketsystem_prefix'];
        $ticketsystem_teammember = $support['ticketsystem_teammember'];

        // Name
        if ($threadUID == 0) {
            $username = $threadUsername.$lang->ticketsystem_guest;
        } else {
            $username = ticketsystem_playername($threadUID);
        }

        $prefixTitle = $db->fetch_field($db->simple_select("ticketsystem", "title", "tsid= ".$ticketsystem_prefix), "title");              
        $bannertext = $lang->sprintf($lang->ticketsystem_banner, $tid, $pid, $username, $prefixTitle);

        // Abgeben
        if ($ticketsystem_teammember == $activeUID) {
            $option = $lang->sprintf($lang->ticketsystem_banner_leave, $tid);
        } 
        // Übernehmen
        else {
            $option = $lang->sprintf($lang->ticketsystem_banner_take, $tid);
        }

        eval("\$ticketsystem_banner .= \"".$templates->get("ticketsystem_banner")."\";");
    }
}

// Misc - Options Link
function ticketsystem_misc() {

    global $db, $mybb, $lang;

    // return if the action key isn't part of the input
    $allowed_actions = [
        'ticketsystem_take',
        'ticketsystem_leave',
        'ticketsystem_solved',
        'ticketsystem_unsolved'
    ];
    if (!in_array($mybb->get_input('action', MyBB::INPUT_STRING), $allowed_actions)) return;

    $lang->load("ticketsystem");
    $activeUID = $mybb->user['uid'];

    // Übernehmen
    if ($mybb->get_input('action') == "ticketsystem_take") {

        $tid = $mybb->get_input('tid');
        $pid = get_thread($tid)['firstpost'];
        $author = get_thread($tid)['uid'];
        $subject = get_thread($tid)['subject'];

        $take_ticket = array(
            "ticketsystem_teammember" => (int)$activeUID,
        );
        $db->update_query("threads", $take_ticket, "tid = ".$tid);

        // MyAlert Stuff
        if(class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
			$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('ticketsystem_alert');
			if ($alertType != NULL && $alertType->getEnabled()) {
				$alert = new MybbStuff_MyAlerts_Entity_Alert((int)$author, $alertType, (int)$mybb->user['uid']);
				$alert->setExtraDetails([
					'username' => $mybb->user['username'],
                    'from' => $mybb->user['uid'],
                    'tid' => $tid,
                    'pid' => $pid,
					'subject' => $subject,
				]);
				MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);   
			}
		}

        redirect("index.php", $lang->ticketsystem_redirect_take);
    }

    // Abgeben
    if ($mybb->get_input('action') == "ticketsystem_leave") {

        $tid = $mybb->get_input('tid');

        $leave_ticket = array(
            "ticketsystem_teammember" => (int)0,
        );
        $db->update_query("threads", $leave_ticket, "tid = ".$tid);

        redirect("index.php", $lang->ticketsystem_redirect_leave);
    }

    // Erledigt
    if ($mybb->get_input('action') == "ticketsystem_solved") {

        $tid = $mybb->get_input('tid');
        $pid = get_thread($tid)['firstpost'];

        $solved_ticket = array(
            "ticketsystem_solved" => (int)1,
        );
        $db->update_query("threads", $solved_ticket, "tid = ".$tid);

        redirect("showthread.php?tid=".$tid."&pid=".$pid."#pid".$pid, $lang->ticketsystem_redirect_solved);
    }

    // Öffnen
    if ($mybb->get_input('action') == "ticketsystem_unsolved") {

        $tid = $mybb->get_input('tid');
        $pid = get_thread($tid)['firstpost'];

        $unsolved_ticket = array(
            "ticketsystem_solved" => (int)0,
        );
        $db->update_query("threads", $unsolved_ticket, "tid = ".$tid);

        redirect("showthread.php?tid=".$tid."&pid=".$pid."#pid".$pid, $lang->ticketsystem_redirect_unsolved);
    }
}

// Forumdisplay
function ticketsystem_forumdisplay() {

    global $templates, $mybb, $lang, $db, $thread, $ticketsystem_teammember, $teamname, $ticketsystem_prefix;

    // Thread- und Foren-ID
    $tid = $thread['tid'];
    $fid = $thread['fid'];

    $supportarea = $mybb->settings['ticketsystem_supportarea'];
    if ($fid != $supportarea) {
        $ticketsystem_teammember = "";
        $ticketsystem_prefix = "";
        return;
    }
    
    $lang->load('ticketsystem');
    $ticketsystem_teammember = "";

    $teammemberUID = $thread['ticketsystem_teammember'];
    if ($teammemberUID != 0) {
        $teamname = ticketsystem_playername($teammemberUID);
        $editedby = $lang->sprintf($lang->ticketsystem_editedby, $teamname);
        eval("\$ticketsystem_teammember = \"".$templates->get("ticketsystem_forumdisplay")."\";");
    }

    $ticketsystem_prefix = $db->fetch_field($db->simple_select("ticketsystem", "displaystyle", "tsid = ".$thread['ticketsystem_prefix']), "displaystyle")."&nbsp;";
}

// Showthread
function ticketsystem_showthread() {
	
	global $mybb, $templates, $thread, $lang, $db, $ticketsystem_teammember, $ticketsystem_button, $ticketsystem_prefix;

    // Thread- und Foren-ID
    $tid = $thread['tid'];
    $fid = $thread['fid'];
    $authorUID = $thread['uid'];

    $supportarea = $mybb->settings['ticketsystem_supportarea'];
    if ($fid != $supportarea) {
        $ticketsystem_teammember = "";
        $ticketsystem_button = "";
        $ticketsystem_prefix = "";
        return;
    }
    
    $lang->load('ticketsystem');
    $ticketsystem_teammember = "";
    $ticketsystem_button = "";

    $teammemberUID = $db->fetch_field($db->simple_select("threads", "ticketsystem_teammember", "tid= ".$tid), "ticketsystem_teammember");
    if ($teammemberUID != 0) {
        $teamname = ticketsystem_playername($teammemberUID);
        $editedby = $lang->sprintf($lang->ticketsystem_editedby, $teamname);
        eval("\$ticketsystem_teammember = \"".$templates->get("ticketsystem_showthread")."\";");
    }
    
    $ticketsystem_prefix = $db->fetch_field($db->simple_select("ticketsystem", "displaystyle", "tsid = ".$thread['ticketsystem_prefix']), "displaystyle")."&nbsp;";

    // Button
    $activeUID = $mybb->user['uid'];
    $teamUIDs = array_keys(ticketsystem_get_allchars($teammemberUID));
    $authorUIDs = array_keys(ticketsystem_get_allchars($authorUID));

    if (in_array($activeUID, $authorUIDs) || in_array($activeUID, $teamUIDs)) {
        $ticketsystem_solved = $db->fetch_field($db->simple_select("threads", "ticketsystem_solved", "tid= ".$tid), "ticketsystem_solved");

        // erledigt
        if ($ticketsystem_solved == 1) {
            $status = "unsolved";
            $solvedStatus = $lang->ticketsystem_button_unsolved;
        }
        // unerledigt
        else {
            $status = "solved";
            $solvedStatus = $lang->ticketsystem_button_solved;
        }
        eval("\$ticketsystem_button = \"".$templates->get("ticketsystem_showthread_button")."\";");
    }
}

### ALERTS ###
// Backwards-compatible alert formatter registration.
function ticketsystem_register_myalerts_formatter_back_compat(){

	global $lang;
	$lang->load('ticketsystem');

	if (function_exists('myalerts_info')) {
		$myalerts_info = myalerts_info();
		if (version_compare($myalerts_info['version'], '2.0.4') <= 0) {
			ticketsystem_register_myalerts_formatter();
		}
	}
}

// Alert formatter registration.
function ticketsystem_register_myalerts_formatter(){

	global $mybb, $lang;
	$lang->load('ticketsystem');

	if (class_exists('MybbStuff_MyAlerts_Formatter_AbstractFormatter') &&
	    class_exists('MybbStuff_MyAlerts_AlertFormatterManager') &&
	    !class_exists('ticketsystemAlertFormatter')
	) {
		class ticketsystemAlertFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
		{
			/**
			* Format an alert into it's output string to be used in both the main alerts listing page and the popup.
			*
			* @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to format.
			*
			* @return string The formatted alert string.
			*/
			public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
			{
				$alertContent = $alert->getExtraDetails();
				return $this->lang->sprintf(
					$this->lang->ticketsystem_alert,
					$outputAlert['from_user'],
					$alertContent['subject']
				);
		
			}

			/**
			* Init function called before running formatAlert(). Used to load language files and initialize other required
			* resources.
			*
			* @return void
			*/
			public function init()
			{
				if (!$this->lang->ticketsystem_alert) {
					$this->lang->load('ticketsystem');
				}
			}
		
			/**
			* Build a link to an alert's content so that the system can redirect to it.
			*
			* @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to build the link for.
			*
			* @return string The built alert, preferably an absolute link.
			*/
			public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
			{
				$alertContent = $alert->getExtraDetails();
				$postLink = $this->mybb->settings['bburl'] . '/' . get_post_link((int)$alertContent['pid'], (int)$alertContent['tid']).'#pid'.(int)$alertContent['pid'];
				return $postLink;
			}
		}

		$formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();
		if (!$formatterManager) {
		        $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
		}
		if ($formatterManager) {
			$formatterManager->registerFormatter(new ticketsystemAlertFormatter($mybb, $lang, 'ticketsystem_alert'));
		}
	}
}

#########################
### PRIVATE FUNCTIONS ###
#########################

// Teammitglieder
function ticketsystem_teammember() {
    
    global $db, $mybb;

    $groups = array_map('intval', explode(',', $mybb->settings['ticketsystem_team']));
    
    $conditions = [];
    foreach ($groups as $gid) {
        $conditions[] = "usergroup = '".$gid."'";
        $conditions[] = "CONCAT(',',additionalgroups,',') LIKE '%,".$gid.",%'";
    }
    $where = implode(' OR ', $conditions);

    $user_query = $db->query("SELECT uid, username FROM ".TABLE_PREFIX."users
    WHERE (".$where.")
    AND as_uid = 0
    ");
    
    $team_users = [];
    while ($user = $db->fetch_array($user_query)) {
        $uid = $user['uid'];
        $playername = ticketsystem_playername($uid);
        $team_users[$uid] = $playername;
    }

    return $team_users;  
}

// Spitzname
function ticketsystem_playername($uid) {
    
    global $db, $mybb;

    $playername_setting = $mybb->settings['ticketsystem_playername'];

    if (!empty($playername_setting)) {
        if (is_numeric($playername_setting)) {
            $playername_fid = "fid".$playername_setting;
            $playername = $db->fetch_field($db->simple_select("userfields", $playername_fid ,"ufid = '".$uid."'"), $playername_fid);
        } else {
            $playername_fid = $db->fetch_field($db->simple_select("application_ucp_fields", "id", "fieldname = '".$playername_setting."'"), "id");
            $playername = $db->fetch_field($db->simple_select("application_ucp_userfields", "value", "uid = '".$uid."' AND fieldid = '".$playername_fid."'"), "value");
        }
    } else {
        $playername = "";
    }

    if (!empty($playername)) {
        $playername = $playername;
    } else {
        $playername = get_user($uid)['username'];
    }

    return $playername;
}

// Errors ACP
function ticketsystem_validate_prefix() {

    global $mybb, $lang;

    $lang->load('ticketsystem');

    $errors = [];

    // Title
    $title = $mybb->get_input('title');
    if (empty($title)) {
        $errors[] = $lang->ticketsystem_form_error_title;
    }

    // Anzeige-Stil
    $displaystyle = $mybb->get_input('displaystyle');
    if (empty($displaystyle)) {
        $errors[] = $lang->ticketsystem_form_error_displaystyle;
    }

    // Teammitglieder
    $teammembers = $mybb->get_input('teammembers', MyBB::INPUT_ARRAY);
    if (empty($teammembers)) {
        $errors[] = $lang->ticketsystem_form_error_teammember;
    }

    return $errors;
}

// ACCOUNTSWITCHER HILFSFUNKTION => Danke, Katja <3
function ticketsystem_get_allchars($user_id) {

	global $db;

    if (intval($user_id) === 0) {
        return array();
    }

	//für den fall nicht mit hauptaccount online
	if (isset(get_user($user_id)['as_uid'])) {
        $as_uid = intval(get_user($user_id)['as_uid']);
    } else {
        $as_uid = 0;
    }

	$charas = array();
	if ($as_uid == 0) {
	  // as_uid = 0 wenn hauptaccount oder keiner angehangen
	  $get_all_users = $db->query("SELECT uid,username FROM ".TABLE_PREFIX."users WHERE (as_uid = ".$user_id.") OR (uid = ".$user_id.") ORDER BY username");
	} else if ($as_uid != 0) {
	  //id des users holen wo alle an gehangen sind 
	  $get_all_users = $db->query("SELECT uid,username FROM ".TABLE_PREFIX."users WHERE (as_uid = ".$as_uid.") OR (uid = ".$user_id.") OR (uid = ".$as_uid.") ORDER BY username");
	}
	while ($users = $db->fetch_array($get_all_users)) {
        $uid = $users['uid'];
        $charas[$uid] = $users['username'];
	}
    // $charas => ['uid' => 'username, '4' => 'Vorname Nachname',...]
	return $charas;  
}

#####################################################
### DATABASE | SETTINGS | TEMPLATES | STYLESHEETS ###
#####################################################

// DATENBANKTABELLE & FELD
function ticketsystem_database() {

    global $db;

    // Präfixe
    if (!$db->table_exists("ticketsystem")) {
        $db->query("CREATE TABLE ".TABLE_PREFIX."ticketsystem(
            `tsid` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `title` varchar(500) NOT NULL,
            `displaystyle` varchar(2000) NOT NULL,
            `teammembers` varchar(5000) NOT NULL,
            PRIMARY KEY(`tsid`),
            KEY `tsid` (`tsid`)
            ) ENGINE=InnoDB ".$db->build_create_table_collation().";"
        );
    }

    // Threads - Präfix
    if (!$db->field_exists("ticketsystem_prefix", "threads")) {
        $db->query("ALTER TABLE `".TABLE_PREFIX."threads` ADD `ticketsystem_prefix` INT(11) unsigned NOT NULL DEFAULT '0';");
    }

    // Threads - Team-UID
    if (!$db->field_exists("ticketsystem_teammember", "threads")) {
        $db->query("ALTER TABLE `".TABLE_PREFIX."threads` ADD `ticketsystem_teammember` INT(11) unsigned NOT NULL DEFAULT '0';");
    }

    // Threads - Done
    if (!$db->field_exists("ticketsystem_solved", "threads")) {
        $db->query("ALTER TABLE `".TABLE_PREFIX."threads` ADD `ticketsystem_solved` INT(1) unsigned NOT NULL DEFAULT '0';");
    }
}

// EINSTELLUNGEN
function ticketsystem_settings($type = 'install') {

    global $db; 

    $setting_array = array(
        'ticketsystem_team' => array(
            'title' => 'Teamgruppen',
            'description' => 'Welche Gruppen dürfen Support-Themen annehmen?',
            'optionscode' => 'groupselect',
            'value' => '4', // Default
            'disporder' => 1
        ),
		'ticketsystem_playername' => array(
			'title' => 'Spitzname',
            'description' => 'Wie lautet die FID / der Identifikator von dem Profilfeld/Steckbrieffeld für den Spitznamen?<br><b>Hinweis:</b> Bei klassischen Profilfeldern muss eine Zahl eintragen werden. Bei dem Steckbrief-Plugin von Risuena muss der Name/Identifikator des Felds eingetragen werden.',
            'optionscode' => 'text',
            'value' => '', // Default
            'disporder' => 2
		),
		'ticketsystem_supportarea' => array(
			'title' => 'Support-Bereich',
			'description' => 'In welchem Forum liegt der Support vom Board?',
			'optionscode' => 'forumselectsingle',
			'value' => '10', // Default
			'disporder' => 3
		),
    );

    $gid = $db->fetch_field($db->write_query("SELECT gid FROM ".TABLE_PREFIX."settinggroups WHERE name = 'ticketsystem' LIMIT 1;"), "gid");

    if ($type == 'install') {
        foreach ($setting_array as $name => $setting) {
          $setting['name'] = $name;
          $setting['gid'] = $gid;
          $db->insert_query('settings', $setting);
        }  
    }

    if ($type == 'update') {

        // Einzeln durchgehen 
        foreach ($setting_array as $name => $setting) {
            $setting['name'] = $name;
            $check = $db->write_query("SELECT name FROM ".TABLE_PREFIX."settings WHERE name = '".$name."'"); // Überprüfen, ob sie vorhanden ist
            $check = $db->num_rows($check);
            $setting['gid'] = $gid;
            if ($check == 0) { // nicht vorhanden, hinzufügen
              $db->insert_query('settings', $setting);
            } else { // vorhanden, auf Änderungen überprüfen
                
                $current_setting = $db->fetch_array($db->write_query("SELECT title, description, optionscode, disporder FROM ".TABLE_PREFIX."settings 
                WHERE name = '".$db->escape_string($name)."'
                "));
            
                $update_needed = false;
                $update_data = array();
            
                if ($current_setting['title'] != $setting['title']) {
                    $update_data['title'] = $setting['title'];
                    $update_needed = true;
                }
                if ($current_setting['description'] != $setting['description']) {
                    $update_data['description'] = $setting['description'];
                    $update_needed = true;
                }
                if ($current_setting['optionscode'] != $setting['optionscode']) {
                    $update_data['optionscode'] = $setting['optionscode'];
                    $update_needed = true;
                }
                if ($current_setting['disporder'] != $setting['disporder']) {
                    $update_data['disporder'] = $setting['disporder'];
                    $update_needed = true;
                }
            
                if ($update_needed) {
                    $db->update_query('settings', $update_data, "name = '".$db->escape_string($name)."'");
                }
            }
        }
    }

    rebuild_settings();
}

// TEMPLATES
function ticketsystem_templates($mode = '') {

    global $db;

    $templates[] = array(
        'title'		=> 'ticketsystem_banner',
        'template'	=> $db->escape_string('<div class="red_alert">{$bannertext} {$option}</div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'ticketsystem_forumdisplay',
        'template'	=> $db->escape_string('<em><span class="smalltext" style="background: url(\'images/nav_bit.png\') no-repeat left; padding-left: 18px;">{$editedby}</span></em><br />'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'ticketsystem_newthread',
        'template'	=> $db->escape_string('<tr>
        <td class="trow1">
		<strong>{$lang->ticketsystem_headline}</strong>
		<div class="smalltext">{$lang->ticketsystem_desc}</div>
        </td>
        <td class="trow1">
		<select name="ticketsystem">{$prefix_options}</select>
        </td>
        </tr>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'ticketsystem_showthread',
        'template'	=> $db->escape_string('<br /><em><span class="smalltext" style="background: url(\'images/nav_bit.png\') no-repeat left; padding-left: 18px;">{$editedby}</span></em>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'ticketsystem_showthread_button',
        'template'	=> $db->escape_string('<a href="misc.php?action=ticketsystem_{$status}&tid={$tid}" class="button new_reply_button"><span>{$solvedStatus}</span></a>&nbsp;'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    if ($mode == "update") {

        foreach ($templates as $template) {
            $query = $db->simple_select("templates", "tid, template", "title = '".$template['title']."' AND sid = '-2'");
            $existing_template = $db->fetch_array($query);

            if($existing_template) {
                if ($existing_template['template'] !== $template['template']) {
                    $db->update_query("templates", array(
                        'template' => $template['template'],
                        'dateline' => TIME_NOW
                    ), "tid = '".$existing_template['tid']."'");
                }
            }   
            else {
                $db->insert_query("templates", $template);
            }
        }
	
    } else {
        foreach ($templates as $template) {
            $check = $db->num_rows($db->simple_select("templates", "title", "title = '".$template['title']."'"));
            if ($check == 0) {
                $db->insert_query("templates", $template);
            }
        }
    }
}

// STYLESHEET MASTER
function ticketsystem_stylesheet() {

    global $db;
    
    $css = array(
		'name' => 'ticketsystem.css',
		'tid' => 1,
		'attachedto' => '',
		'stylesheet' =>	'',
		'cachefile' => 'ticketsystem.css',
		'lastmodified' => TIME_NOW
	);

    return $css;
}

// STYLESHEET UPDATE
function ticketsystem_stylesheet_update() {

    // Update-Stylesheet
    // wird an bestehende Stylesheets immer ganz am ende hinzugefügt
    $update = '';

    // Definiere den  Überprüfung-String (muss spezifisch für die Überprüfung sein)
    $update_string = '';

    return array(
        'stylesheet' => $update,
        'update_string' => $update_string
    );
}

// UPDATE CHECK
function ticketsystem_is_updated(){

    global $db, $mybb;

    if ($db->field_exists("ticketsystem_teammember", "threads")) {
        return true;
    }
    return false;
}
