<?php
error_reporting(E_ALL & ~E_DEPRECATED);
require_once 'vendor/autoload.php';
require 'inc/config.php';
ini_set('memory_limit', MEMORY_LIMIT);
setlocale(LC_ALL, LOCALE);
date_default_timezone_set('Etc/UTC');

use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\DNSCheckValidation;
use Egulias\EmailValidator\Validation\SpoofCheckValidation;
use Egulias\EmailValidator\Validation\MultipleValidationWithAnd;
use Egulias\EmailValidator\Validation\RFCValidation;

$def_options = [
    ['i', 'input', \GetOpt\GetOpt::REQUIRED_ARGUMENT, 'HTML template file'],
    ['?', 'help', \GetOpt\GetOpt::NO_ARGUMENT, 'Show this help and quit'],
	[null, 'mirror-margins', \GetOpt\GetOpt::OPTIONAL_ARGUMENT, 'Use mirror margins', 0],
	[null, 'use-kerning', \GetOpt\GetOpt::OPTIONAL_ARGUMENT, 'Use kerning', false],
	[null, 'format', \GetOpt\GetOpt::OPTIONAL_ARGUMENT, 'Paper format', 'A4-L'],
	[null, 'watermark-image', \GetOpt\GetOpt::OPTIONAL_ARGUMENT, 'Path to Watermark Image', ''],
	[null, 'watermark-image-alpha', \GetOpt\GetOpt::OPTIONAL_ARGUMENT, 'Alpha for Watermark Image', 20],
	[null, 'watermark-image-alpha-blend', \GetOpt\GetOpt::OPTIONAL_ARGUMENT, 'Alpha Blend for Watermark Image', 'Normal'],
	[null, 'watermark-text', \GetOpt\GetOpt::OPTIONAL_ARGUMENT, 'Watermark Text', ''],
	[null, 'watermark-text-alpha', \GetOpt\GetOpt::OPTIONAL_ARGUMENT, 'Alpha for Watermark Text', 20],
    ['d', 'data', \GetOpt\GetOpt::REQUIRED_ARGUMENT, 'CSV file with data to fill template', ''],	
	[null, 'margin-top', \GetOpt\GetOpt::OPTIONAL_ARGUMENT, 'Margin top', 0],
	[null, 'margin-left', \GetOpt\GetOpt::OPTIONAL_ARGUMENT, 'Margin left', 0],
	[null, 'margin-right', \GetOpt\GetOpt::OPTIONAL_ARGUMENT, 'Margin right', 0],
	[null, 'margin-bottom', \GetOpt\GetOpt::OPTIONAL_ARGUMENT, 'Margin bottom', 0],
    ['e', 'email_col', \GetOpt\GetOpt::OPTIONAL_ARGUMENT, 'Email column name in CSV file', 'email'],
    ['o', 'output', \GetOpt\GetOpt::OPTIONAL_ARGUMENT, 'PDF output file/directory', ''],
	['s', 'subject', \GetOpt\GetOpt::OPTIONAL_ARGUMENT, 'Email subject', 'Certificate'],
	['m', 'message', \GetOpt\GetOpt::REQUIRED_ARGUMENT, 'Path to file with message', 'Here is your certificate'],
	[null, 'test', \GetOpt\GetOpt::OPTIONAL_ARGUMENT, 'Test - generate PDF without sending emails'],
	['a', 'attach', \GetOpt\GetOpt::OPTIONAL_ARGUMENT, 'Additional attachment', ''],
	[null, 'parse', \GetOpt\GetOpt::OPTIONAL_ARGUMENT, 'Additional places', ''],
];
$getopt = new \GetOpt\GetOpt($def_options);
try {
    try {
        $getopt->process();
    } catch (Missing $exception) {
        if (!$getopt->getOption('help')) {
            throw $exception;
        }
    }
} catch (Exception $exception) {
    file_put_contents('php://stderr', $exception->getMessage().PHP_EOL);
    echo PHP_EOL.$getopt->getHelpText();
    exit;
}
$options = $getopt->getOptions();


if ($getopt->getOption('help')) {
    echo $getopt->getHelpText();
    exit;
}

// Text
if(!file_exists($options['i'])):
	echo "Can't load HTML template file" . "\r\n";;
    exit;
endif;
$html = file_get_contents($options['i']);

// CSV Data file
$data_file = '';
if(!file_exists($options['data'])):
	echo "CSV file doesn't exist" . "\r\n";
    exit;
endif;
$data_file = $options['data'];

$email_col_name = 'email';
if (isset($options['e'])) {
    $email_col_name = $options['e'];
}

// Output file/directory
$output = 'out';
if (!empty($options['output'])) {
	if(!file_exists($options['output'])):
		echo "Output directory doesn't exist" . "\r\n";
		exit;
	endif;  
	$output = $options['output'];
}

if(!file_exists($options['message'])):
	echo "File email message no exist" . "\r\n";;
	exit;
endif;
$email_message = file_get_contents($options['message']);

$attchments = array();
if(!empty($options['attach'])):
	$t = explode(',', $options['attach']);
	foreach($t as $file):
		if(!file_exists($file)):
			echo "Can't attach: " . $file . "\r\n";
		else:
			$attchments[] = $file;
		endif;		
	endforeach;
endif;

$parseplaces = array();
if(!empty($options['parse'])):
	$t = explode('###', $options['parse']);
	for($i = 0; $i < count($t); $i+=2):
		$parseplaces[$t[$i]] = $t[$i+1];
	endfor;
endif;

if (false !== ($handle = fopen($data_file, 'r'))) {
	$csv_header = fgetcsv($handle, 1000, DELIMITER);
	$send_by_email = in_array($email_col_name, $csv_header);
	$i = 0;

	while (false !== ($data = fgetcsv($handle, 1000, DELIMITER))) {
		if (count($data) > 0) {
			$row = [];
			foreach ($data as $key => $value) {
				$row[trim($csv_header[$key])] = preg_replace('/\x{FEFF}/u', '', $value);
			}
			
			$ff = @file_get_contents($output.DIRECTORY_SEPARATOR.'.done.emails.txt');
			if(preg_match('/' . $row[$email_col_name] . '/', $ff)):
				echo "This email is omitted, because certificate was sent earlier: " . $row[$email_col_name] . "\r\n";
				continue;
			endif;
			
			////////
			$temp_dfile = $html;
			foreach ($row as $key => $value) {
				$temp_dfile = preg_replace('/\{\{\s*'.$key.'\s*\}\}/', trim($value), $temp_dfile);
			}
			$temp_dfile = str_replace('{{ %now% }}', strftime(DATE_FORMAT), $temp_dfile);
			
			foreach ($parseplaces as $key => $value) {
				$temp_dfile = preg_replace('/\{\{\s*'.$key.'\s*\}\}/', trim($value), $temp_dfile);
			}			
		
			///////
			$temail_message = $email_message;
			foreach ($row as $key => $value) {
				$temail_message = preg_replace('/\{\{\s*'.$key.'\s*\}\}/', trim($value), $temail_message);
			}
					
			foreach ($parseplaces as $key => $value) {
				$temail_message = preg_replace('/\{\{\s*'.$key.'\s*\}\}/', trim($value), $temail_message);
			}			
		
			$temail_message = str_replace('{{ %now% }}', strftime(DATE_FORMAT), $temail_message);
			
			print_r($row);

			$output_file = isset($row[$email_col_name]) ? $output.DIRECTORY_SEPARATOR.strtolower(trim($row[$email_col_name])).'.pdf' : $output.DIRECTORY_SEPARATOR.$i.'pdf';
			// create new PDF document
			//MPDF
			//MathJax
			preg_match('/<svg[^>]*>\s*(<defs.*?>.*?<\/defs>)\s*<\/svg>/', $html, $m);
			$defs = @$m[1];
			$html = preg_replace('/<svg[^>]*>\s*<defs.*?<\/defs>\s*<\/svg>/', '', $html);
			$html = preg_replace('/(<svg[^>]*>)/', "\\1".$defs, $html);
			preg_match_all('/<svg([^>]*)style="(.*?)"/', $html, $m);
			for ($i = 0; $i < count($m[0]); $i++) {
				$style=$m[2][$i];

				preg_match('/width: (.*?);/', $style, $wr);
				$w = $mpdf->ConvertSize($wr[1], 0, $mpdf->FontSize) * $mpdf->dpi/25.4;

				preg_match('/height: (.*?);/', $style, $hr);
				$h = $mpdf->ConvertSize($hr[1], 0, $mpdf->FontSize) * $mpdf->dpi/25.4;
			  
				$replace = '<svg'.$m[1][$i].' width="'.$w.'" height="'.$h.'" style="'.$m[2][$i].'"';
				$html = str_replace($m[0][$i], $replace, $html);
			}
			  
			$mpdf = new \Mpdf\Mpdf([
				'mode' => 'utf-8',
				'mirrorMargins' => $options['mirror-margins'],
				'useKerning' => $options['use-kerning'],
				'format' => $options['format'],
				'watermarkImgAlphaBlend' => $options['watermark-image-alpha-blend'],
				'margin_top' => $options['margin-top'],
				'margin_left' => $options['margin-left'],
				'margin_right' => $options['margin-right'],
				'margin_bottom' => $options['margin-bottom'],
			]);
			$mpdf->SetTitle('Certificate: ' . $row[$email_col_name]);
			$mpdf->SetAuthor('Certgen by Nitro <nitro.bystrzyca@gmail.com>');
			$mpdf->SetCreator('Certgen by Nitro <nitro.bystrzyca@gmail.com>');
			$mpdf->SetSubject('Certficate');

			if(file_exists($options['watermark-image'])):
				$mpdf->SetWatermarkImage($options['watermark-image']);
				$mpdf->showWatermarkImage = true;
				$mpdf->watermarkImageAlpha = number_format($options['watermark-image-alpha']/100, 1, '.', '');
			endif;

			if(!empty($options['watermark-text'])):
			$mpdf->SetWatermarkText($options['watermark-text']);
			$mpdf->showWatermarkText = true;
			$mpdf->watermarkTextAlpha = number_format($options['watermark-text-alpha']/100, 1, '.', '');
			endif;

			$mpdf->SetProtection(array('print','print-highres'));
			$mpdf->autoScriptToLang = true;
			$mpdf->baseScript = 1;
			$mpdf->autoVietnamese = true;
			$mpdf->autoArabic = true;
			$mpdf->autoLangToFont = true;
			$mpdf->WriteHTML($temp_dfile);
			$mpdf->Output($output_file, "F");
			//END MPDF
			if(!isset($options['test'])):
				$validator = new EmailValidator();
				$multipleValidations = new MultipleValidationWithAnd([
					new RFCValidation(),
					new DNSCheckValidation(),
					new SpoofCheckValidation(),
				]);
				
				if($validator->isValid($row[$email_col_name], $multipleValidations) != '1'):
					echo 'Email is invalid:' . $row[$email_col_name] . "\r\n";
					continue;
				endif;
							
				$transport = (new Swift_SmtpTransport(MAIL_HOST, MAIL_PORT, SMTP_SECURE))
					->setUsername(MAIL_USERNAME)
					->setPassword(MAIL_PASSWORD)
					->setTimeout(MAIL_TIMEOUT)
				;

				$logger = new Swift_Plugins_Loggers_ArrayLogger();
				$transport->registerPlugin(new Swift_Plugins_LoggerPlugin($logger));

				// Create the Mailer using your created Transport
				$mailer = new Swift_Mailer($transport);
				$mailer->registerPlugin(new Swift_Plugins_AntiFloodPlugin(MAIL_ANTIFLOOD_EMAILS, MAIL_ANTIFLOOD_PAUSE));
				// Create a message
				$message = (new Swift_Message($options['subject']))
					->setFrom([MAIL_USERNAME => EMAIL_FROM])
					->setTo($row[$email_col_name])
					->setBody(strip_tags($temail_message), 'text/plain')
					->addPart($temail_message, 'text/html')
					->attach(Swift_Attachment::fromPath($output_file))
				;
				foreach($attchments as $att):
					$message->attach(Swift_Attachment::fromPath($att));
				endforeach;
				
				if($mailer->send($message)):
					echo "Sent email: " . $row[$email_col_name] . "\r\n";
					$ff = @file_get_contents($output.DIRECTORY_SEPARATOR.'.done.emails.txt');
					file_put_contents($output.DIRECTORY_SEPARATOR.'.done.emails.txt', $ff . $row[$email_col_name] . ';');
				else:
					echo 'Problem with send email:' . $row[$email_col_name] . "\r\n";
				endif;
			endif;
			++$i;
		}
	}

	fclose($handle);
}
