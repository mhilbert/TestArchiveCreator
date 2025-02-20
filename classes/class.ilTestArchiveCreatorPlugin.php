<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

include_once("Services/UIComponent/classes/class.ilUserInterfaceHookPlugin.php");
 
/**
 * Basic plugin file
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 *
 */
class ilTestArchiveCreatorPlugin extends ilUserInterfaceHookPlugin
{
	const PASS_ALL = 'all';
	const PASS_SCORED = 'scored';

	const ORIENTATION_PORTRAIT = 'portrait';
	const ORIENTATION_LANDSCAPE = 'landscape';

	const STATUS_INACTIVE = 'inactive';
	const STATUS_PLANNED = 'planned';
	const STATUS_FINISHED = 'finished';
	const STATUS_RUNNING = 'running';

	const RANDOM_ALL = 'all';
	const RANDOM_USED = 'used';


	/** @var ilTestArchiveCreatorConfig */
	protected $config;


	/** @var ilTestArchiveCreatorSettings[] */
	protected $settings = [];


	/**
	 * Get the plugin name
	 * @return string
	 */
	public function getPluginName()
	{
		return "TestArchiveCreator";
	}

	/**
	 * Get the global configuration
	 * @return ilTestArchiveCreatorConfig
	 */
	public function getConfig()
	{
		if (!isset($this->config)) {
			$this->includeClass('class.ilTestArchiveCreatorConfig.php');
			$this->config = new ilTestArchiveCreatorConfig($this);
		}
		return $this->config;
	}


	/**
	 * Get the settings for a test object
	 * @param int $obj_id test object id
	 * @return ilTestArchiveCreatorSettings
	 */
	public function getSettings($obj_id)
	{
		if (!isset($this->settings[$obj_id])) {
			$this->includeClass('class.ilTestArchiveCreatorSettings.php');
			$this->settings[$obj_id] = new ilTestArchiveCreatorSettings($this, $obj_id);
		}
		return $this->settings[$obj_id];
	}


	/**
	 * Get the archive creator
	 * @param $obj_id
	 * @return ilTestArchiveCreator
	 */
	public function getArchiveCreator($obj_id)
	{
		$this->includeClass('class.ilTestArchiveCreator.php');
		return new ilTestArchiveCreator($this, $obj_id);
	}


	/**
     * Check if the player plugin is active
     * @return bool
     */
	public function checkCronPluginActive()
    {
    	global $DIC;
		/** @var ilPluginAdmin $ilPluginAdmin */
    	$ilPluginAdmin = $DIC['ilPluginAdmin'];

        return $ilPluginAdmin->isActive('Services', 'Cron', 'crnhk', 'TestArchiveCron');
    }

	/**
	 * Handle a call by the cron job plugin
	 * @return	int		Number of created archives
	 * @throws	Exception
	 */
    public function handleCronJob()
	{
        global $DIC;

		$this->includeClass('class.ilTestArchiveCreatorSettings.php');
		$this->includeClass('class.ilTestArchiveCreator.php');

        // manual cron job execution in the admin gui
        if (ilContext::usesHTTP())
        {
           // save the current controller parameters to be restored afterwards
            $params = $DIC->http()->request()->getQueryParams();
            $ref_id = $params['ref_id'];
            $base_class = $params['baseClass'];
            $cmd_class = $params['cmdClass'];
            $node = $params['cmdNode'];
        }

        // initialize controller for the Question GUI
        $ctrl = $DIC->ctrl();
        $ctrl->initBaseClass('ilUIPluginRouterGUI');
        $ctrl->setCmdClass('iltestarchivecreatorsettingsgui');

        $created = 0;
        foreach (ilTestArchiveCreatorSettings::getScheduledObjects() as $obj_id)
        {
            $creator = new ilTestArchiveCreator($this, $obj_id);
            if ($creator->createArchive()) {
                $creator->settings->status = self::STATUS_FINISHED;
                $creator->settings->save();
                $created++;
            }
            unset($creator);
        }

        // manual cron job execution in the admin gui
        if (ilContext::usesHTTP())
        {
            // restore the controller status
            // this allows a proper redirection after the return from the job run
            $ctrl->initBaseClass($base_class);
            $ctrl->setCmdClass($cmd_class);
            $ctrl->setParameterbyClass($base_class, 'ref_id', $ref_id);
            $ctrl->current_node = $node;
        }


        return $created;
	}

	/**
	 * Build the exam id and allow ids without active_id and pass
	 * @param ilObjTest $testObj
	 * @param null $active_id
	 * @param null $pass
	 * @return string
	 */
	public function buildExamId($testObj, $active_id = null, $pass = null)
	{
		global $DIC;
		/** @var ilSetting $ilPluginAdmin */
		$ilSetting = $DIC['ilSetting'];


		$inst_id = $ilSetting->get( 'inst_id', null );
		$obj_id = $testObj->getId();

		$examId = 'I' . $inst_id . '_T' . $obj_id;

		if (isset($active_id))
		{
			$examId .=  '_A' . $active_id;
		}

		if (isset($pass))
		{
			$examId .= '_P' . $pass;
		}

		return $examId;
	}

	/**
	 * Build a full question id like the exam id
	 * @param ilObjTest $testObj
	 * @param $question_id
	 * @return string
	 */
	public function buildExamQuestionId($testObj, $question_id)
	{
		return $this->buildExamId($testObj). '_Q' . $question_id;
	}

	/**
	 * Cleanup when uninstalling
	 */
	public function beforeUninstall()
	{
		global $DIC;
		$ilDB = $DIC->database();
		$ilDB->dropTable('tarc_ui_settings');

		return parent::beforeUninstall();
	}


	/**
	 * Check if the user has administrative access
	 * @return bool
	 */
	public function hasAdminAccess()
	{
		global $rbacsystem;
		return $rbacsystem->checkAccess("visible", SYSTEM_FOLDER_ID);
	}

}
