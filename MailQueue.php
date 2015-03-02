<?php 

/**
 * MailQueue.php
 * @author Saranga Abeykoon http://nterms.com
 */

namespace nterms\mailqueue;

use Yii;
use yii\swiftmailer\Mailer;
use yii\swiftmailer\Message;
use nterms\models\Queue;

/**
 * MailQueue is a sub class of [yii\switmailer\Mailer](https://github.com/yiisoft/yii2-swiftmailer/blob/master/Mailer.php) 
 * which intends to replace it.
 * 
 * Configuration is the same as in `yii\switmailer\Mailer` with some additional properties to control the mail queue
 * 
 * ~~~
 * 	'components' => [
 * 		...
 * 		'mailqueue' => [
 * 			'class' => 'nterms\mailqueue\MailQueue',
 *			'table' => '{{%mail_queue}}',
 *			'mailsPerRound' => 10,
 *			'maxAttempts' => 3,
 * 			'transport' => [
 * 				'class' => 'Swift_SmtpTransport',
 * 				'host' => 'localhost',
 * 				'username' => 'username',
 * 				'password' => 'password',
 * 				'port' => '587',
 * 				'encryption' => 'tls',
 * 			],
 * 		],
 * 		...
 * 	],
 * ~~~
 * 
 * @see http://www.yiiframework.com/doc-2.0/yii-swiftmailer-mailer.html
 * @see http://www.yiiframework.com/doc-2.0/ext-swiftmailer-index.html
 * 
 * This extension replaces `yii\switmailer\Message` with `nterms\mailqueue\Message' 
 * to enable queuing right from the message.
 * 
 */
class MailQueue extends Mailer
{
	const NAME = 'mailqueue';
	
	/**
     * @var string message default class name.
     */
    public $messageClass = 'nterms\mailqueue\Message';
	
	/**
	 * @var string the name of the database table to store the mail queue.
	 */
	public $table = '{{%mail_queue}}';
	
	/**
	 * @var integer the default value for the number of mails to be sent out per processing round.
	 */
	public $mailsPerRound = 10;
	
	/**
	 * @var integer maximum number of attempts to try sending an email out.
	 */
	public $maxAttempts = 3;
	
	/**
	 * Initializes the MailQueue component.
	 */
	public function init()
	{
		parent::init();
		
		if(Yii::$app->db->getTableSchema($this->table) == null) {
			throw new InvalidConfigException('"' . $this->table . '" not found in database. Make sure the db migration is properly done and the table is created.');
		}
	}
	
	/**
	 * Sends out the messages in email queue and update the database.
	 * 
	 * @return boolean true if all messages are successfully sent out
	 */
	public function process()
	{
		$success = true;
		
		$items = Queue::find()->where(['and', ['sent_time' => null], ['<', 'attempts', $this->maxAttempts]])->orderBy(['queued_time' => SORT_ASC])->limit($this->mailsPerRound)->all();
		
		if(!empty($items)) {
			foreach($items as $item) {
				if($message = $item->toMessage()) {
					$attributes = ['attempts', 'last_attempt_time'];
					
					if($this->sendMessage($message)) {
						$item->sent_time = time();
						$attributes[] = 'sent_time';
					} else {
						$success = false;
					}
					
					$item->attempts = $item->attempts + 1;
					$item->last_attempt_time = time();
					
					$item->updateAttributes($attributes);
				}
			}
		}
		
		return $success;
	}
}