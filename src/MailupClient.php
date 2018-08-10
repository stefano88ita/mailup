<?php

namespace Caereservices\Mailup;

use Caereservices\Mailup\MailupStatus as MailupStatus;
use Caereservices\Mailup\MailupException as MailupException;
use Caereservices\Mailup\MailupClass as MailupClass;

/**
*  MailupClient Class - A Mailup.com platform API Interface
*  https://github.com/caereservices/mailup
*
*  @author Massimo Villalta
*/
class MailupClient {

   private $mailUp = null;
   private $clientLogged = false;
   private $listId = -1;

   function __construct($inClientId = "", $inClientSecret = "", $inCallbackUri = "") {
      try {
         if( ($inClientId != "") && ($inClientSecret != "") && ($inCallbackUri != "") ) {
            $this->mailUp = new MailupClass($inClientId, $inClientSecret, $inCallbackUri);
         }
      } catch (MailUpException $e) {
         // DO NOTHING AT THE MOMENT
      }
   }

   /*
       PRIVATE METHOD
   */
   protected function makeRecipientsRequest($userData , $_dynafields) {
      if( !isset($userData["mail"]) || $userData["mail"] == "" ) return "";
      $fields = [];
      $dynafields = json_decode($_dynafields, true);
      foreach( $dynafields["Items"] as $df ) {
         if( $df["Description"] == "firstname" ) {
            if( isset($userData["name"]) && $userData["name"] != "" ) {
               $df["Value"] = $userData["name"];
               $fields[] = $df;
            }
         }
         if( $df["Description"] == "lastname" ) {
            if( isset($userData["surname"]) && $userData["surname"] != "" ) {
               $df["Value"] = $userData["surname"];
               $fields[] = $df;
            }
         }
         if( $df["Description"] == "phone" ) {
            if( isset($userData["mobile"]) && $userData["mobile"] != "" ) {
               $df["Value"] = $userData["mobile"];
               $fields[] = $df;
            }
         }
         if( $df["Description"] == "company" ) {
            if( isset($userData["company"]) && $userData["company"] != "" ) {
               $df["Value"] = $userData["company"];
               $fields[] = $df;
            }
         }
      }
      $retVal = [
         "Email" => $userData["mail"],
         "Fields" => $fields
      ];
      if( isset($userData["mobile"]) && $userData["mobile"] != "" ) {
         $retVal["MobileNumber"] = $userData["mobile"];
         $retVal["MobilePrefix"] = '0039';
      }
      return json_encode($retVal);
   }

   protected function create_list($listName = "", $list = []) {
      $listId = -1;
      if( $listName != "" ) {
         $listId = $this->get_list_id($listName);
         if( $listId == -1 ) {
            if( count($list) > 0 ) {
               $listData = [
                  "Name" => $list["name"],
                  "Business" => true,
                  "Customer" => true,
                  "OwnerEmail" => $list["main_mail"],
                  "ReplyTo" => $list["reply_to"],
                  "NLSenderName" => $list["sender_name"],
                  "CompanyName" => $list["company_name"],
                  "ContactName" => $list["contact_name"],
                  "Address" => $list["address"],
                  "City" => $list["city"],
                  "CountryCode" => $list["country_code"],
                  "PermissionReminder" => $list["perm_remind"],
                  "WebSiteUrl" => $list["web_site"],
                  "UseDefaultSettings" => true
               ];
               try {
                  $url = $this->mailUp->getConsoleEndpoint() . "/Console/List";
                  $result = $this->mailUp->callMethod($url, "POST", $listData, "JSON");
                  if( $result === false ) return $listId;
                  $result = json_decode($result);
                  if( isset($result->IdList) ) {
                     $listId = $result->IdList;
                  }
               } catch (MailUpException $e) {
                  // DO NOTHING AT THE MOMENT
               }
            } else {
               $listId = 1;
            }
         }
      }
      return $listId;
   }

   protected function get_list_id($listName = "") {
      $listId = -1;
      if( $listName != "" ) {
         try {
            $url = $this->mailUp->getConsoleEndpoint() . "/Console/List";
            $result = $this->mailUp->callMethod($url, "GET", null, "JSON");
            if( $result === false ) return $listId;
            $result = json_decode($result);
            $arr = $result->Items;
            for( $i = 0; $i < count($arr); $i++ ) {
               $list = $arr[$i];
               if( $listName == $list->Name) {
                  $listId = $list->IdList;
                  break;
               }
            }
         } catch (MailUpException $e) {
            // DO NOTHING AT THE MOMENT
         }
      }
      return $listId;
   }

   protected function get_user_id($mail = "") {
      $itemID = -1;
      if( $mail != "" && $this->listId != -1 ) {
         try {
            $url = $this->mailUp->getConsoleEndpoint() . "/Console/List/" . $this->listId . "/Recipients/Subscribed?filterby=\"Email.Contains('" . $mail . "')\"";
            $result = $this->mailUp->callMethod($url, "GET", null, "JSON");
            if( $result === false ) return $itemID;
            $result = json_decode($result);
            if( count($result->Items) > 0 ) {
               $itemID = $result->Items[0]->idRecipient;
            }
         } catch (MailUpException $e) {
            // DO NOTHING AT THE MOMENT
         }
      }
      return $itemID;
   }

   protected function get_group_id($groupName = "") {
      $groupId = -1;
      if( $groupName != "" && $this->listId != -1 ) {
         try {
            $url = $this->mailUp->getConsoleEndpoint() . "/Console/List/" . $this->listId . "/Groups";
            $result = $this->mailUp->callMethod($url, "GET", null, "JSON");
            if( $result === false ) return $groupId;
            $result = json_decode($result);
            if( isset($result->Items) ) {
               $arr = $result->Items;
               for( $i = 0; $i < count($arr); $i++ ) {
                  $group = $arr[$i];
                  if( $groupName == $group->Name) {
                     $groupId = $group->idGroup;
                     break;
                  }
               }
            }
         } catch (MailUpException $e) {
            // DO NOTHING AT THE MOMENT
         }
      }
      return $groupId;
   }

   protected function create_group($groupName = "") {
      $groupId = -1;
      if( $groupName != "" && $this->listId != -1 ) {
         try {
            $url = $this->mailUp->getConsoleEndpoint() . "/Console/List/" . $this->listId . "/Group";
            $groupRequest = "{\"Deletable\":true,\"Name\":\"" . $groupName . "\",\"Notes\":\"". $groupName . "\"}";
            $result = $this->mailUp->callMethod($url, "POST", $groupRequest, "JSON");
            if( $result === false ) return $groupId;
            $result = json_decode($result);
            $arr = $result->Items;
            for( $i = 0; $i < count($arr); $i++ ) {
               $group = $arr[$i];
               if( $groupName == $group->Name) {
                  $groupId = $group->idGroup;
                  break;
               }
            }
         } catch (MailUpException $e) {
            // DO NOTHING AT THE MOMENT
         }
      }
      return $groupId;
   }

   protected function get_attachment($attach = "") {
      if( $attach != "" ) {
         $att = ["Data" => "", "Name" => ""];
         if( file_exists($attach) || (substr(strtolower($attach), 0, 5) == "http:") ) {
            $tmp = file_get_contents($attach);
            if( $tmp !== false ) {
               $att["Data"] = base64_encode($tmp);
               $att["Name"] = pathinfo($attach, PATHINFO_BASENAME);
               return $att;
            }
         }
         return MailupStatus::ERR_ATTACH_FILE_NOT_EXIST;
      }
      return [];
   }

   protected function add_attachment($emailId = 0, $attach = []) {
      try {
         $attachReq = [
            "Base64Data" => $attach["Data"],
            "Name" => ($attach["Name"] != "" ? $attach["Name"] : "Allegato_1"),
            "Slot" => 1,
            "idList" => $this->listId,
            "idMessage" => $emailId
         ];
         $url = $this->mailUp->getConsoleEndpoint() . "/Console/List/" . $this->listId . "/Email/" . $emailId . "/Attachment/1";
         $result = $this->mailUp->callMethod($url, "POST", json_encode($attachReq), "JSON");
         $result = json_decode($result);
      } catch (MailUpException $e) {
         // DO NOTHING AT THE MOMENT
      }
   }

   protected function create_mail_from_template($templateId = 0, $attach = []) {
      try {
         $url = $mailUp->getConsoleEndpoint() . "/Console/List/" . $this->listId . "/Email/Template/" . $templateId;
         $result = $mailUp->callMethod($url, "POST", null, "JSON");
         $result = json_decode($result);
         if( count($result) > 0 ) {
            $emailId = $result[0]->idMessage;
            if( $emailId != 0 ) {
               if( count($attach) > 0 ) {
                  $this->add_attachment($emailId, $attach);
               }
               return $emailId;
            }
         }
      } catch (MailUpException $e) {
         // DO NOTHING AT THE MOMENT
      }
      return false;
   }

   protected function create_mail_from_message($subject = "", $message = "", $attach = []) {
      try {
         $email = [
            "Subject" => $subject,
            "idList" => $this->listId,
            "Content" => $message,
            "Embed" => true,
            "IsConfirmation" => true,
            "Fields" => [],
            "Notes" => "",
            "Tags" => [],
            "TrackingInfo" => [
               "CustomParams" => "",
               "Enabled" => true,
               "Protocols" => ["http", "https"]
            ]
         ];
         $url = $this->mailUp->getConsoleEndpoint() . "/Console/List/" . $this->listId . "/Email";
         $result = $this->mailUp->callMethod($url, "POST", json_encode($email), "JSON");
         $result = json_decode($result);
         $emailId = $result->idMessage;
         if( $emailId != 0 ) {
            if( count($attach) > 0 ) {
               $this->add_attachment($emailId, $attach);
            }
            return $emailId;
         }
      } catch (MailUpException $e) {
         // DO NOTHING AT THE MOMENT
      }
      return false;
   }

   protected function send_mail_array($emailId = 0, $userMails = []) {
      try {
         foreach( $userMails as $userMail ) {
            $userId = $this->get_user_id($userMail);
            if( $userId > 0 ) {
               $url = $this->mailUp->getConsoleEndpoint() . "/Console/Email/Send";
               $tmpData = [
                  "Email" => $userMail,
                  "idMessage" => $emailId
               ];
               $postData = json_encode($tmpData);
               $result = $this->mailUp->callMethod($url, "POST", $postData, "JSON");
            }
         }
         return true;
      } catch (MailUpException $e) {
         // DO NOTHING AT THE MOMENT
      }
      return false;
   }

   protected function send_mail($emailId = 0, $groupName = "", $userMail) {
      $postData = null;
      $url = $this->mailUp->getConsoleEndpoint() . "/Console/List/" . $this->listId . "/Email/" . $emailId . "/Send";
      if( $groupName != "" ) {
         $groupId = $this->get_group_id($groupName);
         if( $groupId > 0 ) {
            $url = $this->mailUp->getConsoleEndpoint() . "/Console/Group/" . $groupId . "/Email/" . $emailId . "/Send";
         } else {
            return false;
         }
      }
      if( isset($userMail) && !is_array($userMail) && $userMail != "" ) {
         $userMail = [$userMail];
      }
      if( isset($userMail) && is_array($userMail) ) {
         return $this->send_mail_array($emailId, $userMail);
      } else {
         try {
            $result = $this->mailUp->callMethod($url, "POST", $postData, "JSON");
            return true;
         } catch (MailUpException $e) {
            // DO NOTHING AT THE MOMENT
         }
      }
      return false;
   }

   /*
       PUBLIC METHOD
   */
   function login($user = "", $password = "", $listName = "") {
      if( $this->mailUp && ($user != "") && ($password != "") ) {
         try {
            $this->clientLogged = $this->mailUp->logOnWithPassword($user, $password);
            if( $this->clientLogged ) {
               if( $listName != "" ) {
                  $this->listId = $this->get_list_id($listName);
                  if( $this->listId == -1 ) {
                     $this->listId = $this->create_list($listName);
                     if( $this->listId > 0 ) {
                        return MailupStatus::OK;
                     }
                     return MailupStatus::ERR_LIST_NOT_FOUND;
                  }
               } else {
                  $this->listId = 1;
               }
               return MailupStatus::OK;
            }
            return MailupStatus::ERR_NOT_LOGGED_IN;
         } catch ( MailUpException $e ) {
            return MailupStatus::ERR_MAILUP_EXCEPTION;
         }
      }
      return MailupStatus::ERR_INVALID_PARAMETER;
   }

   function createList($listName = "", $listData = []) {
       if( count($listData) > 0 ) {
          if( !isset($listData["name"]) || ($listData["name"] == "") ) {
             return MailupStatus::ERR_INVALID_LIST_DATA;
          }
          if( !isset($listData["main_mail"]) || ($listData["main_mail"] == "") ) {
             return MailupStatus::ERR_INVALID_LIST_DATA;
          }
          if( !isset($listData["reply_to"]) || ($listData["reply_to"] == "") ) {
             return MailupStatus::ERR_INVALID_LIST_DATA;
          }
          if( !isset($listData["sender_name"]) || ($listData["sender_name"] == "") ) {
             return MailupStatus::ERR_INVALID_LIST_DATA;
          }
          if( !isset($listData["company_name"]) || ($listData["company_name"] == "") ) {
             return MailupStatus::ERR_INVALID_LIST_DATA;
          }
          if( !isset($listData["contact_name"]) || ($listData["contact_name"] == "") ) {
             return MailupStatus::ERR_INVALID_LIST_DATA;
          }
          if( !isset($listData["address"]) || ($listData["address"] == "") ) {
             return MailupStatus::ERR_INVALID_LIST_DATA;
          }
          if( !isset($listData["city"]) || ($listData["city"] == "") ) {
             return MailupStatus::ERR_INVALID_LIST_DATA;
          }
          if( !isset($listData["country_code"]) || ($listData["country_code"] == "") ) {
             return MailupStatus::ERR_INVALID_LIST_DATA;
          }
          if( !isset($listData["web_site"]) || ($listData["web_site"] == "") ) {
             return MailupStatus::ERR_INVALID_LIST_DATA;
          }
          if( !isset($listData["perm_remind"]) ) {
             $listData["perm_remind"] = "";
          }
          return $this->changeList($listName, $listData);
       }
       return MailupStatus::ERR_NO_LIST_DATA;
   }

   function changeList($listName = "", $listData = []) {
      if( $this->clientLogged ) {
         if( $listName != "" ) {
            try {
               $listId = $this->get_list_id($listName);
               if( $listId == -1 ) {
                  if( count($listData) > 0 ) {
                     $listId = $this->create_list($listName, $listData);
                     if( $listId > 0 ) {
                        $this->listId = $listId;
                        return MailupStatus::OK;
                     }
                     return MailupStatus::ERR_LIST_NOT_CREATED;
                  } else {
                     return MailupStatus::ERR_LIST_NOT_CHANGED;
                  }
               }
            } catch (MailUpException $e) {
               return MailupStatus::ERR_MAILUP_EXCEPTION;
            }
         }
         return MailupStatus::ERR_INVALID_PARAMETER;
      }
      return MailupStatus::ERR_NOT_LOGGED_IN;
   }

   function delUserFromGroup($mail = "", $groupName = "") {
      if( $this->clientLogged ) {
         if( $mail != "" && $groupName != "" && $this->listId != -1 ) {
            try {
               $itemID = $this->get_user_id($mail);
               $groupId = -1;
               if( intval($groupName) > 0 ) {
                  $groupId = intval($groupName);
               } else {
                  $groupId = $this->get_group_id($groupName);
               }
               if( $groupId != -1 && $itemID != -1 ) {
                  $url = $this->mailUp->getConsoleEndpoint() . "/Console/Group/" . $groupId . "/Unsubscribe/" . $itemID;
                  $result = $this->mailUp->callMethod($url, "DELETE", null, "JSON");
                  return MailupStatus::OK;
               } else {
                  return MailupStatus::ERR_GETTING_DATA;
               }
               return MailupStatus::ERR_USERDATA_NOTFOUND;
            } catch (MailUpException $e) {
               return MailupStatus::ERR_MAILUP_EXCEPTION;
            }
         } else {
            return MailupStatus::ERR_INVALID_PARAMETER;
         }
      } else {
         return MailupStatus::ERR_NOT_LOGGED_IN;
      }
   }

   function addGroup($groupName = "") {
      if( $this->clientLogged ) {
         if( $groupName != "" ) {
            try {
               $groupId = $this->get_group_id($groupName);
               if( $groupId == -1 ) {
                  $groupId = $this->create_group($groupName);
               }
               if( $groupId != -1 ) {
                  return $groupId;
               }
               return MailupStatus::ERR_CREATING_GROUPS;
            } catch (MailUpException $e) {
               return MailupStatus::ERR_MAILUP_EXCEPTION;
            }
         } else {
            return MailupStatus::ERR_INVALID_PARAMETER;
         }
      } else {
         return MailupStatus::ERR_NOT_LOGGED_IN;
      }
   }

   function addUserToGroup($userData = [], $groupName = "") {
      if( $this->clientLogged ) {
         if( is_array($userData) && (count($userData) > 0) && ($groupName != "") ) {
            try {
               $groupId = -1;
               if( intval($groupName) > 0 ) {
                  $groupId = intval($groupName);
               } else {
                  $groupId = $this->get_group_id($groupName);
                  if( $groupId == -1 ) {
                     $groupId = $this->create_group($groupName);
                  }
               }
               if( $groupId != -1 ) {
                  $itemID = $this->get_user_id($userData["mail"]);
                  if( $itemID > 0 ) {
                     return MailupStatus::OK;
                  } else {
                     $url = $this->mailUp->getConsoleEndpoint() . "/Console/Recipient/DynamicFields?PageNumber=0&PageSize=30&orderby=\"Id+asc\"";
                     $result = $this->mailUp->callMethod($url, "GET", null, "JSON");
                     if( $result === false ) return MailupStatus::ERR_GETTING_FIELDS;
                     $recipientRequest = $this->makeRecipientsRequest($userData, $result);
                     if( $recipientRequest != "" ) {
                        $url = $this->mailUp->getConsoleEndpoint() . "/Console/Group/" . $groupId . "/Recipient";
                        $result = $this->mailUp->callMethod($url, "POST", $recipientRequest, "JSON");
                        if( $result === false ) return MailupStatus::ERR_INVALID_USERDATA;
                        return MailupStatus::OK;
                     }
                     return MailupStatus::ERR_INVALID_USERDATA;
                  }
               }
               return MailupStatus::ERR_ADDING_USER;
            } catch (MailUpException $e) {
               return MailupStatus::ERR_MAILUP_EXCEPTION;
            }
         } else {
            return MailupStatus::ERR_INVALID_PARAMETER;
         }
      } else {
         return MailupStatus::ERR_NOT_LOGGED_IN;
      }
   }

   function getTemplateList() {
      if( $this->clientLogged ) {
         try {
            if( $this->listId != -1 ) {
               $url = $this->mailUp->getConsoleEndpoint() . "/Console/List/" . $this->listId . "/Templates";
               $result = $this->mailUp->callMethod($url, "GET", null, "JSON");
               $retVal = json_decode($result);
               if( count($retVal) == 0 ) {
                  return MailupStatus::ERR_NO_TEMPLATES;
               }
               return $retVal;
            } else {
               return MailupStatus::ERR_UNKNOW_LIST;
            }
         } catch (MailUpException $ex) {
            return MailupStatus::ERR_MAILUP_EXCEPTION;
         }
      }
      return MailupStatus::ERR_NOT_LOGGED_IN;
   }

   function sendFromTemplate($templateId = 0, $groupName = "", $userMail, $attach = "") {
      if( $this->clientLogged ) {
         if( $templateId != 0 ) {
            $attachData = $this->get_attachment($attach);
            if( !is_array($attachData) ) {
               return $attachData;
            }
            $result = $this->create_mail_from_template($templateId, $attachData);
            if( (gettype($result) == "integer") && (intval($result) != 0) ) {
               if( $this->send_mail($result, $groupName, $userMail) ) {
                  return MailupStatus::MESSAGE_SENDED;
               } else {
                  return MailupStatus::ERR_MESSAGE_NOT_SENDED;
               }
            } else {
               return MailupStatus::ERR_CANT_CREATE_MESSAGE;
            }
         } else {
            return MailupStatus::ERR_NO_TEMPLATES;
         }
      }
      return MailupStatus::ERR_NOT_LOGGED_IN;
   }

   function sendMessage($subject = "", $message = "", $groupName = "", $userName, $attach = "") {
      if( $this->clientLogged ) {
         if( $subject != "" && $message != "" ) {
            $attachData = $this->get_attachment($attach);
            if( !is_array($attachData) ) {
               return $attachData;
            }
            $result = $this->create_mail_from_message($subject, $message, $attachData);
            if( (gettype($result) == "integer") && (intval($result) != 0) ) {
               if( $this->send_mail($result, $groupName, $userName) ) {
                  return MailupStatus::MESSAGE_SENDED;
               } else {
                  return MailupStatus::ERR_MESSAGE_NOT_SENDED;
               }
            } else {
               return MailupStatus::ERR_CANT_CREATE_MESSAGE;
            }
         } else {
            return MailupStatus::ERR_MESSAGE_TEXT_EMPTY;
         }
      }
      return MailupStatus::ERR_NOT_LOGGED_IN;
   }

}
