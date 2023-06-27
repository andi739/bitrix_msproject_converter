<?php

$IMPLEMENTATION_USED;

const DO_HIERARCHY_TAGS = false;

/**
 * takes a csv file and returns an associative array with column names as keys
 * 
 * @param mixed $filetext
 * @param string $delimiter
 * @param bool $drop_unnecessary
 * 
 * @return [type]
 */
function make_associative_array_csv($filetext, $delimiter = '#', $drop_unnecessary = true) {
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
 * takes raw .csv text and returns an array of Task objects
 * 
 * @param mixed $filetext
 * @param string $delimiter
 * @param bool $drop_unnecessary
 * 
 * @return [type]
 */
function make_tasks_from_csv($filetext, $delimiter = '#', $drop_unnecessary = true) {
   $transformed_content = make_associative_array_csv($filetext, $delimiter, $drop_unnecessary);

   $keys = array_keys($transformed_content); //$table[$keys[i]] for indexing of associative array
   //Create a task object for every row
   $taskArray = [];
   for($i=0; $i < count($transformed_content[$keys[0]]); $i++) {
      $task = new Task;
      $task->setTitle($transformed_content['Vorgangsname'][$i]);
      $task->setStartDatePlan($transformed_content['Anfangstermin'][$i]);
      $task->setEndDatePlan($transformed_content['Endtermin'][$i]);
      //$task->setDeadline($transformed_content['Spätestes_Ende'][$i]);
      $task->setDeadline($transformed_content['Endtermin'][$i]);
      $task->setHierarchyLevel($transformed_content['PSP_Code'][$i]);
      $task->setHierarchyTags();
      array_push($taskArray, $task);
   }

   return $taskArray;
}

/**
 * Returns a file variable. use open(Bitrix\Main\IO\FileStreamOpenMode::READ) and close()
 * 
 * @param mixed $userId
 * @param mixed $fileName
 * @param null $folderName
 * 
 * @return [type]
 */
function get_bitrix_file_handle($userId, $fileName, $folderName = null) {
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
     if ((mb_substr($filenameInternal, 0, 1) == '/') && ($file instanceof Bitrix\Main\IO\File)) {
         return $file;
     } else {
      throw new Exception("Could not get File Handle");
     }
     /* TO USE:
         $src = $file->open(Bitrix\Main\IO\FileStreamOpenMode::READ);
         $return_string = stream_get_contents($src);
         $file->close();
     */
}

/**
 * reads (buffered) a xml-file and returns an array of Task objects
 * 
 * @param mixed $userId
 * @param mixed $fileName
 * @param null $folderName
 * @param int $byteLength
 * 
 * @return [type]
 */
function _make_tasks_from_xml($userId, $fileName, $folderName = null, $byteLength = 5000) {
   try{
      $file = get_bitrix_file_handle($userId, $fileName, $folderName);
      $src = $file->open(Bitrix\Main\IO\FileStreamOpenMode::READ);

      $tmp_str = "";
      while(!strpos($tmp_str, "<Tasks>")) {
         $current_chunk_str = stream_get_contents($src, $byteLength); 
         //safety measure incase the source file corrupted so that the loop doesn't run for ever
         if($current_chunk_str == "") {
            break;
         }
         //keep the last chars incase the keyword has started e.g. <tas
         //the rest is unnessecary
         $tmp_str = substr($tmp_str, 0, -10);
         $tmp_str .= $current_chunk_str;
      }
      //now we are in the <tasks></tasks> part, the where all the relevant data is.
      $taskArray = [];
      while(true) { 
         while(!($pos_task_begin = strpos($tmp_str, "<Task>")) || !($pos_task_end = strpos($tmp_str, "</Task>"))) {
            //when we've found </Tasks>, but no <Task>, there must be no tasks left and therefore we break out of both(2) while loops
            if(strpos($tmp_str, "</Tasks>")) {
               echo "\nfound </Tasks>"; 
               break 2;
            }
                 
            $current_chunk_str = stream_get_contents($src, $byteLength); 
            //safety measure incase the source file corrupted so that the loop doesn't run for ever
            if($current_chunk_str == "") {
               break 2;
            }
            $tmp_str .= $current_chunk_str;

         }
         //now we have a task
         if($pos_task_begin < $pos_task_end) {
            //fill task object and add to $taskArray
            $task_str = substr($tmp_str,$pos_task_begin, $pos_task_end-$pos_task_begin+strlen("</Task>"));

            $task_tags = ["Name", "WBS", "Start", "Finish", "LateFinish"];
            $tmp_arr = [];

            foreach ($task_tags as $tag) {
               $pos_begin  = strpos($task_str, "<".$tag.">"); 
               $pos_end = strpos($task_str, "</".$tag.">");
               array_push($tmp_arr, trim(substr($task_str, $pos_begin+strlen("<".$tag.">"), $pos_end-$pos_begin-strlen("</".$tag.">")+1)));
            }
            //add task as array to array
            $task = new Task;
            $task->setTitle($tmp_arr[0]);
            $task->setHierarchyLevel($tmp_arr[1]);
            $task->setStartDatePlan($tmp_arr[2]);
            $task->setEndDatePlan($tmp_arr[3]);
            //$task->setDeadline($tmp_arr[4]);
            $task->setDeadline($tmp_arr[3]);
            $task->setHierarchyTags();
            array_push($taskArray, $task);

            //remove everything from string until first </task> including the </task> !!!
            $tmp_str = substr($tmp_str, $pos_task_end+strlen("</Task>"));
                 
         } else {
            throw new Exception("Invalid Task Cursors! start_cursor: ".$pos_task_begin."   end_cursor: ".$pos_task_end);
         }

               

      }

      $file->close();
             
   } catch(IO\IoException $e) {
      echo 'Caught exception: ',  $e->getMessage(), "\n";
      return false;
   }
   return $taskArray;
}

/**
 * reads a xml-file using the simplexml functionality and returns an array of Task objects
 * 
 * @param mixed $userId
 * @param mixed $fileName
 * @param null $folderName
 * 
 * @return [type]
 */
function make_tasks_from_xml($userId, $fileName, $folderName = null) {
   $taskArray = [];
   try {
      $file = get_bitrix_file_handle($userId, $fileName, $folderName);
      $src = $file->open(Bitrix\Main\IO\FileStreamOpenMode::READ);
      $xml_string = stream_get_contents($src);
      $file->close();

      $xml = new SimpleXMLElement($xml_string);       
      foreach($xml->Tasks->Task as $xmlTask)  {
         $task = new Task;
         $task->setTitle((string)$xmlTask->Name);
         $task->setHierarchyLevel((string)$xmlTask->WBS);
         $task->setStartDatePlan((string)$xmlTask->Start);
         $task->setEndDatePlan((string)$xmlTask->Finish);
         //$task->setDeadline((string)$xmlTask->LateFinish);
         $task->setDeadline((string)$xmlTask->Finish);
         $task->setHierarchyTags();
         array_push($taskArray, $task);
         }


             
             
   } catch(IO\IoException $e) {
      echo 'Caught exception: ',  $e->getMessage(), "\n";
      return false;
   }
   return $taskArray;
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
   try {
      $file = get_bitrix_file_handle($userId, $fileName, $folderName);
      $src = $file->open(Bitrix\Main\IO\FileStreamOpenMode::READ);
      $return_string = stream_get_contents($src);
      $file->close();
           
      }
      catch(IO\IoException $e) {
         echo 'Caught exception: ',  $e->getMessage(), "\n";
         return false;
      }


   return $return_string;
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
   private $hierarchy_level;
   private $bitrix_id;
   private array $tags;

   public function __construct() {
      $this->tags = [];
   }
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
      elseif(preg_match("/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/",$date)) {
         return substr($date, 8,2).".".substr($date, 5,2).".".substr($date, 0,4);
      }
      elseif($date == "") {
         return "";
      }
      else {
          throw new Exception('Unsupported Date Format!');
      }
  }

  /**
   * returns a Task out of an array that matches the hierarchy level
   * 
   * @param Task[] $task_array
   * @param mixed $hierarchy_level
   * 
   * @return [type]
   */
  public static function get_task_by_hierarchy($task_array, $hierarchy_level) {
   foreach($task_array as $task) {
      if($task instanceof Task) {
         if($task->getHierarchyLevel() == $hierarchy_level) {
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
   if(strlen($this->hierarchy_level) < 2) {
      return null;
   }
   else {
      //get last . position
      $dotPos = strrpos($this->hierarchy_level, ".");
      $parent_hierarchy = substr($this->hierarchy_level,0, $dotPos);
      $parentTask = Task::get_task_by_hierarchy($taskArray, $parent_hierarchy);
      return $parentTask->getBitrixId();
   }
  }

     /**
   * returns the bitrix id of a task's parent
   * 
   * @param mixed $taskArray
   * 
   * @return [type]
   */
  public function getParentName($taskArray) {
   if(strlen($this->hierarchy_level) < 2) {
      return null;
   }
   else {
      //get last . position
      $dotPos = strrpos($this->hierarchy_level, ".");
      $parent_hierarchy = substr($this->hierarchy_level,0, $dotPos);
      $parentTask = Task::get_task_by_hierarchy($taskArray, $parent_hierarchy);
      return $parentTask->getTitle();
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
    * Get the value of hierarchy_level
    */
   public function getHierarchyLevel()
   {
      return $this->hierarchy_level;
   }

   /**
    * Set the value of hierarchy_level
    */
   public function setHierarchyLevel($hierarchy_level): self
   {
      $this->hierarchy_level = $hierarchy_level;

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
    * returns a tag for the hierarchy of the task
    * the root has one tag, all others have 2. the own hierarchy and the parent's
    *
    * @return self
    */
   public function setHierarchyTags(): self
   {
      if(DO_HIERARCHY_TAGS) {
         $val = $this->hierarchy_level;
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
      }
     return $this;
   }

   public function addTags(...$tags): self {
      foreach($tags as $tag) {
         array_push($this->tags, $tag);
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
 * @param mixed $group_id
 * @param mixed $userId
 * @param mixed $fileName
 * @param null $folderName
 * 
 * @return [type]
 */
function add_tasks_from_file($userId, $responsible_id, $group_id, $fileName, $folderName = null) {
   $taskArray;
   if(preg_match("/^.*\.csv$/", $fileName)) {
      $IMPLEMENTATION_USED = "Custom - csv";
      $filetext = getFileContents($userId, $fileName, $folderName);
      $taskArray = make_tasks_from_csv($filetext);
   }
   elseif(preg_match("/^.*\.xml$/", $fileName)) {
      if(function_exists("simplexml_load_file")){
         $IMPLEMENTATION_USED = "simplexml - xml";
         $taskArray = make_tasks_from_xml($userId, $fileName, $folderName);
      } else { //custom xml parsing, if necessary lib for simple-xml is not included
         $IMPLEMENTATION_USED = "Custom - xml";
         $taskArray = _make_tasks_from_xml($userId, $fileName, $folderName);
      }
      
   } else {
      throw new Exception("Unsupported file type given!");
   }
   

  //Check if hierarchy of tasks is <= 2
  $is2d = true; 
  foreach($taskArray as $task) {
      if(!preg_match("/^(\d+)(.(\d*)){0,2}$/", $task->getHierarchyLevel())) {
         $is2d = false;
         break;
      }    
   }
   //add tag für l1: Aufgabenbereich
   //add tag für l1 & (l2 falls vorg. von l1): Name des l1 als Tag
   echo $is2d ? 'Hierarchy is 2d: true' : 'Hierarchy is 2d: false';
   if($is2d) {
      foreach($taskArray as $task) {
         if(preg_match("/^(\d+)(.(\d*)){1}$/",$task->getHierarchyLevel())) {
            $task->addTags("Aufgabenbereich", $task->getTitle());
         } elseif(preg_match("/^(\d+)(.(\d*)){2}$/",$task->getHierarchyLevel())) {
            $task->addTags($task->getParentName($taskArray));
         }
      }
   }


   //create tasks in bitrix
   foreach($taskArray as $task) {
      $task->setBitrixId(add_task($task->getArFields(), $responsible_id, 
      $userId, $group_id));
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
   $var_file_name = $root->GetVariable('file_name');
   //parse because in bitrix you only get a link
   //[url=/bitrix/tools/bizproc_show_file.
   //php?f=paragraph.csv&i=4525&h=6b0837619af9f9d235659e7f98af333f]paragraph.csv[/url] 
   if(gettype($var_file_name)=="array")
      $var_file_name = $var_file_name[0];
   $pos1 = strpos($var_file_name, "]") +1;
   $pos2 = strpos($var_file_name, "[/url]");
   $parsed_file_name = substr($var_file_name, $pos1, $pos2-$pos1);

   add_tasks_from_file($userId, $var_responsible_id, $var_group_id, $parsed_file_name, "Hochgeladene Dateien");   
}



/**
 * use this instead if you run the code in the dev console 
 * 
 * @return [type]
 */
function run_in_console($userId, $var_responsible_id, $var_group_id, $var_file_name) { 
   add_tasks_from_file($userId, $var_responsible_id, $var_group_id, $var_file_name, "Hochgeladene Dateien");   
}



/**
 * returns the Id of the user currently logged in
 * global $USER does no longer work in bitrix, may be fixed? therefore the alternative route throu session context
 * 
 * @return [type]
 */
function getUserId() {
   global $USER;
   $userId = $USER->GetID();
   if($userId == null) {
      $dec = json_decode($_SESSION["SESS_AUTH"]["CONTEXT"]);
      $userId = $dec->userId;
   }
   return $userId;
}



$userId = getUserId();

//run_in_console($userId, $userId, $var_group_id, $var_file_name);

$rootActivity = $this->GetRootActivity();
run_in_workflow($rootActivity, $userId);

echo "\n-----Implementation used: ".$IMPLEMENTATION_USED." Max RAM usage: ".memory_get_peak_usage(true)*(10**-6)."MB-----\n";

?>
