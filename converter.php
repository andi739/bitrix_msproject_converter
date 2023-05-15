<?php


/**
 * takes a csv file and returns an associative array with column names as keys
 * 
 * @param mixed $filetext
 * @param string $delimiter
 * @param bool $drop_unnecessary
 * 
 * @return [type]
 */
function make_associative_array($filetext, $delimiter = '#', $drop_unnecessary = true) {
   //sonst breaken die umlaute
   $filetext_encoded = utf8_encode($filetext);

   $textLines = explode("\n", $filetext_encoded);
   if(strpos($textLines[0],"sep=") !== false) {
      //get the separator and remove 0th line
      $delimiter = $textLines[0][4];
      array_shift($textLines);  
   }
   //put file contents into an associative array, so we can work better with the data!

   //get column names
   $column_names = explode($delimiter, $textLines[0]);
   array_shift($textLines);
   //create array without body
   $table = [];
   foreach($column_names as $column) {
      $table += [$column => []];
   }

   $keys = array_keys($table); //$table[$keys[i]] for indexing of associative array

   //insert values for the key value pairs
   foreach($textLines as $line) {
      $fields = explode($delimiter, $line);
      //skip if empty line
      if(count($fields) == 1) {
         continue;
      }

      if(count($table) != count($fields)) {
         throw new Exception('Amount of Column elements ('.count($table).') and Field elements ('.count($fields).') is unequal,'
         .'perhaps the delimiter char is used in the text somewhere?');
      } else {
         for($i=0; $i < count($fields);$i++) {
            array_push($table[$keys[$i]], $fields[$i]);
         }
      }
   }
   //remove unnecessary columns -> if $drop_unnecessary = true
   if($drop_unnecessary) {
      foreach($keys as $key) { //Spätestes_Ende = Frist, //PSP_Code: Teilaufgabe von, Tags (für das Manuell verwendete system)
         if($key != "Vorgangsname" && $key != "Anfangstermin" && $key != "Endtermin" && $key != "Spätestes_Ende" && $key != "PSP_Code") {
            unset($table[$key]);
         }   
      }
   }
   /*PSP_Code am Beispiel MEnsy
   var_dump($table['PSP_Code']); -> string(11) "1.5.5.4.1.1"
    = (1)Master Energie.... _> (5)MEnsy- Kursdurchfürhung _> (5)5.Sem _> (4)Abschluss Teilnehmer 
   _> (1)Ausstellung der Abschlussdok _> (1) Weiterleitung an Dekan .... */
   return $table;
}

/**
 *reads a text file in someone's drive and returns their content as a string 
 * 
 * @param mixed $userId
 * @param mixed $fileName
 * @param null $folderName
 * 
 * @return [type]
 */
function getFileContents($userId, $fileName, $folderName = null) {
   //we get the "file", that doesn't hold the actual content lol
   if (\Bitrix\Main\Loader::includeModule('disk')) {  
       $storage = \Bitrix\Disk\Driver::getInstance()->getStorageByUserId($userId);
       if($folderName == null) {
           $folder = $storage->getRootObject();
       } else {
           $folder = $storage->getChild(array('=NAME' => $folderName,  
           'TYPE' => \Bitrix\Disk\Internals\FolderTable::TYPE_FOLDER));
       }
      $file = $folder->getChild(array('=NAME' => $fileName, 
        'TYPE' => \Bitrix\Disk\Internals\FileTable::TYPE_FILE)); 

   }
   else {
       throw new Exception('Could not load \'disk\' module.');
   } 
   $arFile = $file->getFileId(); 
   
   $content_type = "";
   $filenameInternal = '';


   if ($arFile = CFile::GetFileArray($arFile)) {
       $filenameInternal = $arFile['SRC'];
   }
   else {
     throw new Exception('Filename was empty.');
   }

   if(isset($arFile["CONTENT_TYPE"])) {
       $content_type = $arFile["CONTENT_TYPE"];
   }
   //we produce resized jpg for original bmp
   if($content_type == '' || $content_type == "image/bmp") {
       if(isset($arFile["tmp_name"])) {
           $content_type = CFile::GetContentType($arFile["tmp_name"], true);
       }
       else {
           $content_type = CFile::GetContentType($_SERVER["DOCUMENT_ROOT"].$filenameInternal);
       }
   }

   if($arFile["ORIGINAL_NAME"] <> '')
       $name = $arFile["ORIGINAL_NAME"];
   elseif($arFile["name"] <> '')
       $name = $arFile["name"];
   else
       $name = $arFile["FILE_NAME"];
   if(isset($arFile["EXTENSION_SUFFIX"]) && $arFile["EXTENSION_SUFFIX"] <> '')
       $name = mb_substr($name, 0, -mb_strlen($arFile["EXTENSION_SUFFIX"]));

   $name = str_replace(array("\n", "\r"), '', $name);



   $content_type = CFile::NormalizeContentType($content_type);
   $src = null;
   $file = null;

   if (mb_substr($filenameInternal, 0, 1) == '/') {
       $file = new Bitrix\Main\IO\File($_SERVER['DOCUMENT_ROOT']. $filenameInternal);
   }
   elseif (isset($arFile['tmp_name'])) {
       $file = new Bitrix\Main\IO\File($arFile['tmp_name']);
   }
   $return_string = '';
   //now we get the actual file contents using a stream
   if ((mb_substr($filenameInternal, 0, 1) == '/') && ($file instanceof Bitrix\Main\IO\File)) {
       try {
           $src = $file->open(Bitrix\Main\IO\FileStreamOpenMode::READ);
           $return_string = stream_get_contents($src);
           $file->close();
           
       }
       catch(IO\IoException $e) {
           echo 'Caught exception: ',  $e->getMessage(), "\n";
           return false;
       }
   }

   return $return_string;
}

/**
 * creates a task with given parameters
 * 
 * @deprecated
 * 
 * @param mixed $title
 * @param mixed $start_date_plan
 * @param mixed $end_date_plan
 * @param mixed $deadline
 * @param mixed $description=""
 * @param mixed $responsible_id=669
 * @param mixed 
 * 
 * @return [type]
 */
function __add_task($title, $start_date_plan, $end_date_plan, $deadline, $description="", 
         $responsible_id=669, $creator_id=null, $group_id="") {
   if (CModule::IncludeModule("tasks")) {
      $arFields = Array(
         "TITLE" => $title,
         "DESCRIPTION" => $description,
         "RESPONSIBLE_ID" => $responsible_id,
         //if null the person, who started the workflow is the creator
         "CREATED_BY" => $creator_id, 
         "GROUP_ID" => $group_id,
         "START_DATE_PLAN" => $start_date_plan,
         "END_DATE_PLAN" => $end_date_plan,
   		"DEADLINE" => $deadline
   		//"MATCH_WORK_TIME"
         //"TAGS"
         //"DEPENDS_ON" = vorherige aufgabe, relevant für gantt?
         //"PARENT_ID" = Teilaufgabe von?
       );

      $obTask = new CTasks;
      $ID = $obTask->Add($arFields);
      $success = ($ID>0);

      if($success) {
         echo "Ok!";
      }
      else {
         if($e = $APPLICATION->GetException())
            echo "Error: ".$e->GetString();  
      }
   } 
   else {
      throw new Exception('Bitrix Task Module could not be included. No tasks were created.');
   }
   return $ID;
}

/**
 * creates a task in bitrix
 * 
 * @param mixed $arFields
 * @param mixed $responsible_id=669
 * @param mixed $creator_id=null
 * @param mixed $group_id=""
 * 
 * @return [type]
 */
function add_task($arFields, $responsible_id=669, $creator_id=null, $group_id="") {
   $arFields["RESPONSIBLE_ID"] = $responsible_id;
   $arFields["CREATED_BY"] = $creator_id;
   $arFields["GROUP_ID"] = $group_id;
   
   if (CModule::IncludeModule("tasks")) {
      $obTask = new CTasks;
      $ID = $obTask->Add($arFields);
      $success = ($ID>0);

      if($success) {
         echo "added!";
      }
      else {
         if($e = $APPLICATION->GetException())
            echo "Error: ".$e->GetString();  
      }
   } 
   else {
      throw new Exception('Bitrix Task Module could not be included. No tasks were created.');
   }
   return $ID;
}

/**
 * updated a task with given values in an associative array
 * 
 * @param mixed $arFields
 * @param mixed $ID
 * 
 * @return [type]
 */
function update_task($arFields, $ID) {
   if (CModule::IncludeModule("tasks")) {
      $obTask = new CTasks;
      $success = $obTask->Update($ID, $arFields);

      if($success)
      {
           echo "updated!";

      }
      else {
           if($e = $APPLICATION->GetException())
               echo "Error: ".$e->GetString();
      }
   }
   else {
      throw new Exception('Bitrix Task Module could not be included. No tasks were created.');
   }
}

/**
 * Task Class that holds parameters necessary for creating a Bitrix Task
 */
class Task {
   private $title;
   private $start_date_plan;
   private $end_date_plan;
   private $deadline;
   private $description;
   private $responsible_id;
   private $creator_id;
   private $group_id;
   private $hierarcy_level;
   private $bitrix_id;
   private $tags;

   /**
    * returns a date without litarals, if e.g. Mon 22.02.10
    *
    * @param mixed $date
    * 
    * @return [type]
    */
   public static function format_date($date) {
      if(preg_match("/^[a-zA-Z]{3}( )\d{2}(\.)\d{2}(\.)(\d{2}|\d{4})$/", $date)) {
          return substr($date, 4);
      }
      elseif(preg_match("/^\d{2}(\.)\d{2}(\.)(\d{2}|\d{4})$/", $date)) {
          return $date;
      }
      elseif($date == "") {
         return "";
      }
      else {
          throw new Exception('Unsupported Date Format!');
      }
  }

  /**
   * returns a Task out of an array that matches the hierarcy level
   * 
   * @param Task[] $task_array
   * @param mixed $hierarcy_level
   * 
   * @return [type]
   */
  public static function get_task_by_hierarcy($task_array, $hierarcy_level) {
   foreach($task_array as $task) {
      if($task instanceof Task) {
         if($task->getHierarcyLevel() == $hierarcy_level) {
            echo "found parent";
            return $task;
         }
      }
      else {
         throw new \InvalidArgumentException('Array must consist of Task objects!');
      }
   }
  }

  /**
   * returns the bitrix id of a task's parent
   * 
   * @param mixed $taskArray
   * 
   * @return [type]
   */
  public function getParentId($taskArray) {
   if(strlen($this->hierarcy_level) < 2) {
      return null;
   }
   else {
      //get last . position
      $dotPos = strrpos($this->hierarcy_level, ".");
      $parent_hierarcy = substr($this->hierarcy_level,0, $dotPos);
      $parentTask = Task::get_task_by_hierarcy($taskArray, $parent_hierarcy);
      return $parentTask->getBitrixId();
   }
  }

   /**
    * Get the value of title
    */
   public function getTitle()
   {
      return $this->title;
   }

   /**
    * Set the value of title
    */
   public function setTitle($title): self
   {
      $this->title = $title;

      return $this;
   }

   /**
    * Get the value of start_date_plan
    */
    public function getStartDatePlan()
    {
       return $this->start_date_plan;
    }
 
    /**
     * Set the value of start_date_plan
     */
    public function setStartDatePlan($start_date_plan): self
    {
       $this->start_date_plan = Task::format_date($start_date_plan);
 
       return $this;
    }   

   /**
    * Get the value of end_date_plan
    */
   public function getEndDatePlan()
   {
      return $this->end_date_plan;
   }

   /**
    * Set the value of end_date_plan
    */
   public function setEndDatePlan($end_date_plan): self
   {
      $this->end_date_plan = Task::format_date($end_date_plan);

      return $this;
      
   }

   /**
    * Get the value of deadline
    */
   public function getDeadline()
   {
      return $this->deadline;
   }

   /**
    * Set the value of deadline
    */
   public function setDeadline($deadline): self
   {
      $this->deadline = Task::format_date($deadline);

      return $this;

   }

   /**
    * Get the value of description
    */
   public function getDescription()
   {
      return $this->description;
   }

   /**
    * Set the value of description
    */
   public function setDescription($description): self
   {
      $this->description = $description;

      return $this;
   }

   /**
    * Get the value of responsible_id
    */
   public function getResponsibleId()
   {
      return $this->responsible_id;
   }

   /**
    * Set the value of responsible_id
    */
   public function setResponsibleId($responsible_id): self
   {
      $this->responsible_id = $responsible_id;

      return $this;
   }

   /**
    * Get the value of creator_id
    */
   public function getCreatorId()
   {
      return $this->creator_id;
   }

   /**
    * Set the value of creator_id
    */
   public function setCreatorId($creator_id): self
   {
      $this->creator_id = $creator_id;

      return $this;
   }

   /**
    * Get the value of group_id
    */
   public function getGroupId()
   {
      return $this->group_id;
   }

   /**
    * Set the value of group_id
    */
   public function setGroupId($group_id): self
   {
      $this->group_id = $group_id;

      return $this;
   }

   /**
    * Get the value of hierarcy_level
    */
   public function getHierarcyLevel()
   {
      return $this->hierarcy_level;
   }

   /**
    * Set the value of hierarcy_level
    */
   public function setHierarcyLevel($hierarcy_level): self
   {
      $this->hierarcy_level = $hierarcy_level;

      return $this;
   }

   /**
   * Get the value of bitrix_id
   */
   public function getBitrixId()
   {
      return $this->bitrix_id;
   }
 
    /**
     * Set the value of bitrix_id
     */
   public function setBitrixId($bitrix_id): self
   {
      $this->bitrix_id = $bitrix_id;

      return $this;
   }

   /**
    * returns a tag for the hierarcy of the task
    * the root has one tag, all others have 2. the own hierarcy and the parent's
    *
    * @return self
    */
   public function setTags(): self
   {
      $this->tags = [];
      $val = $this->hierarcy_level;
      //only one number / Root
      if (preg_match("/^\d*$/", $val)) {
         //_ is used instead of the dot because . is ignored by the system
         array_push($this->tags, $val."_");

      }
      //one number and >1 .d 's / every node below root
      elseif (preg_match("/^\d*(\.(\d)+)+$/", $val)) {
         //because the bitrix search only matches the search query (contains(string)), there is only one tag needed as the system return all tagged items within the searched hierarchy and below
         $replaced = str_replace(".", "_", $val);
         array_push($this->tags, $replaced."_");
      }
      else {
         throw new Exception('Invalid psp_Code: '.$val.'Something with the formatting went wrong!');
      }
     return $this;
   }

   /**
    * Get the value of tags 
    *
    * @return [type]
    */
   public function getTags() {
      return $this->tags;
   }

   /**
    * Returns the arFields needed for creating a Bitrix Task 
    *
    * @return [type]
    */
   public function getArFields($taskArray=null) {
      $arFields = [];
      $arFields['TITLE'] = $this->getTitle();
      $arFields['DESCRIPTION'] = $this->getDescription();
      $arFields['START_DATE_PLAN'] = $this->getStartDatePlan();
      $arFields['END_DATE_PLAN'] = $this->getEndDatePlan();
      $arFields['DEADLINE'] = $this->getDeadline();
      $arFields['TAGS'] = $this->getTags();
      if($taskArray != null) {
         $parent_id = $this->getParentId();
         if($parent_id != null) {
            $arFields['PARENT_ID'] = $parent_id;
         }
         
      }
      //RESPONSIBLE_ID, CREATED_BY, GROUP_ID will be initialized later

      return $arFields;
   }
}

/**
 * combined function, to get file content, transform it and finally create the tasks in bitrix
 * 
 * @param mixed $responsible_id
 * @param mixed $creator_id
 * @param mixed $group_id
 * @param mixed $userId
 * @param mixed $fileName
 * @param null $folderName
 * 
 * @return [type]
 */
function add_tasks_from_file($responsible_id, $creator_id, $group_id, $userId, $fileName, $folderName = null) {
   $filetext = getFileContents($userId, $fileName, $folderName);
   $transformed_content = make_associative_array($filetext);

   $keys = array_keys($transformed_content); //$table[$keys[i]] for indexing of associative array
   //Create a task object for every row
   $taskArray = [];
   for($i=0; $i < count($transformed_content[$keys[0]]); $i++) {
      $task = new Task;
      $task->setTitle($transformed_content['Vorgangsname'][$i]);
      $task->setStartDatePlan($transformed_content['Anfangstermin'][$i]);
      $task->setEndDatePlan($transformed_content['Endtermin'][$i]);
      $task->setDeadline($transformed_content['Spätestes_Ende'][$i]);
      $task->setHierarcyLevel($transformed_content['PSP_Code'][$i]);
      $task->setTags();
      array_push($taskArray, $task);
   }

   //create tasks in bitrix
   foreach($taskArray as $task) {
      $task->setBitrixId(add_task($task->getArFields(), $responsible_id, 
      $creator_id, $group_id));
   }


   
   
   //update tasks in bitrix so they have a father
   foreach($taskArray as $task) {
      $parent_id = $task->getParentId($taskArray);
      if($parent_id != null) {
         $tmp_arr = [];
         $tmp_arr['PARENT_ID'] = $parent_id;

         echo("bitrix id - kid: ". $task->getBitrixId(). " parent: ". $parent_id);

         update_task($tmp_arr, $task->getBitrixId());
      }
      
   }
   
}

/**
 * use this in the actual workflow
 * 
 * @return [type]
 */
function run_in_workflow($root, $userId) {
   
   $var_group_id = $root->GetVariable('group_id');
   $var_responsible_id = $root->GetVariable('responsible_id');
   if($var_responsible_id == null) {
      $var_responsible_id = 669;
   }   
   $var_creator_id = $root->GetVariable('creator_id');
   if($var_creator_id == null) {
    $var_creator_id = 669;
   }
   $var_file_name = $root->GetVariable('file_name');
   //parse because in bitrix you only get a link
   //[url=/bitrix/tools/bizproc_show_file.
   //php?f=paragraph.csv&i=4525&h=6b0837619af9f9d235659e7f98af333f]paragraph.csv[/url] 
   $var_file_name = $var_file_name[0];
   $pos1 = strpos($var_file_name, "]") +1;
   $pos2 = strpos($var_file_name, "[/url]");
   $parsed_file_name = substr($var_file_name, $pos1, $pos2-$pos1);

   add_tasks_from_file($var_responsible_id, $var_creator_id, $var_group_id,
                        $userId, $parsed_file_name, "Hochgeladene Dateien");   
}



/**
 * use this instead if you run the code in the dev console 
 * 
 * @return [type]
 */
function run_in_console() { 
   $var_responsible_id = 660;
   $var_creator_id = 660;
   $var_group_id = 25;
   $userId = 660;
   $var_file_name = "raute.csv";
   add_tasks_from_file($var_responsible_id, $var_creator_id, $var_group_id,
                        $userId, $var_file_name, "Hochgeladene Dateien");   
}

$rootActivity = $this->GetRootActivity();
global $USER;
$userId = $USER->GetID();
run_in_workflow($rootActivity, $userId);

?>



