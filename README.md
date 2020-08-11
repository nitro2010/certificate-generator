# PDF Certificate Generator
PHP script to generate certificates for participation in events.

This project is modified version PDF Certificate Generator created by zedomel.
This script uses MPDF library to generate PDF's from HTML templates.
Given a CSV file with person's details and data to be printed into PDF's file is possible create customized certificates including background images.
There are a range of options (listed bellow) to customize certificates.
After send email, attendant email is write to file .done.emails.txt.
If you will try generate certificate for specific attendant, it will ommitted (remove this file before next, fresh generation of PDF files - it is in output folder).

## Instalation
Main file is: certgen.php
Download
Set values in inc/config.php

## Creating a HTML template

The recommend way to create HTML template is using a HTML editor or a text editor. But it's also possible to use converting tools like [ZAMZAR](https://www.zamzar.com/convert/ppt-to-html/) or [PowerPoint2HTML](https://www.idrsolutions.com/online-powerpoint-to-html5-converter/) to convert PowerPoint presentation to HTML.

The problem with using conversion tools is that sometimes the HTML created contains tags not handle by MPDF and the generate PDF certificate will not work correctly.
**Be sure that you HTML template contains only MPDF supported tags.**
Refer to [MPDF documentation](https://mpdf.github.io/) to get more details about supported tags.

CSS styles must be inline into HTML files in order to be handle by MPDF.

# HTML templace placeholders

It's possible to use placeholders in HTML template to be replaced by data from CSV files (see bellow). The placeholders must use the Jinja syntax, for example:
```xml
<p>{{ first_name }}</p>
```
for define placeholder for a "variable" called `first_name`inside a HTML paragraph tag. The PHP script will search for any placeholder (`{{ * }}`) and replaces it by the value from a column with same name in the CSV file provided as argument to the script (`-d` option, see bellow).

A special placeholder `{{ %now% }}` is available and will be replaced by the current date when generating certificates.

# Creating a PDF certificate

The main file of Certificate Generator is `certgen.php`. To get a list of available options run:
```sh
php certgen.php --help
```

# Options

There are a set of options available to customize the certificate generator that should be passed at command line:
* `-i` or `--input` **(required)**: the HTML template file with placeholders in Jinja format (e.g.: `{{ first_name }}`).
* `?` or `--help`: show help and quit.
* `mirror-margins`: Use mirror margins.
* `use-kerning`: Use kerning.
* `format`: Paper format.
* `watermark-image`: Path to Watermark Image.
* `watermark-image-alpha`: Alpha for Watermark Image.
* `watermark-image-alpha-blend`: Alpha Blend for Watermark Image.
* `watermark-text`: Watermark Text.
* `watermark-text-alpha`: Alpha for Watermark Text.
* `-d`or `--data` **(required)**: CSV file with header at first line that will be used as source to replace placeholders in HTML template. The header (column) names must be the same as in template HTML.
* `margin-top`: Margin top.
* `margin-left`: Margin left.
* `margin-right`: Margin right.
* `margin-bottom`: Margin bottom.
* `-e`or `--email_col`: the email column's name in CSV data file (default: `email`). If a column with that name is present in CSV file then the generated certificates will be sent to attendants emails.
* `-o` or `--output`: PDF directory (default: `out`).
* `-s` or `--subject`: the subject of emails sent to attendants (default: `Certificate`).
* `-m` or `--message` **(required)**: a text or a path to file with the message (body) of the email sent to attendants (default: `Here is yoor certificate`).
* `-a`or `--attach`: addiotional attachments to sent in emails. You can provide as many attachments you want by provinding multiple arguments for this parameter (e.g. `-a path-to-attachment-1.pdf -a path-to-attachment-2.txt`).
* `test`: generate PDF without sending emails.
* `parse`: Parse additional placeholders: format: placeholder###what is to replace###placeholder2###what is to replace2....

# Configuration file

The are a number of configurations that can be customized in `inc/config.php` file. Edit this file if as you need to customize script settings.
* `DELIMITER`: CSV file delimmiter (default: `,`).
* `DATE_FORMAT`: date format.
* `MEMORY_LIMIT`: Memory limit (default: `512M`).
* `LOCALE`: locale (default: `en_US`).
* `MAIL_HOST`: SMTP host (default: `stmp.gmail.com`).
* `MAIL_PORT`: SMTP port (default: `587`).
* `SMTP_SECURE`: Use SMTP TLS encryptation (default: `tls`).
* `EMAIL_FROM`: Name of email Sender.
* `MAIL_USERNAME`: SMTP user name (default: `yourname@gmail.com`).
* `MAIL_PASSWORD`: SMTP password (default: `password123`).
* `MAIL_TIMEOUT`: max time for send email
* `MAIL_ANTIFLOOD_EMAILS`: max email which are sending
* `MAIL_ANTIFLOOD_PAUSE`: pause in seconds

## Examples:

See examples files in `examples` folder:
```sh
php certgen.php -i examples\sample.html -d examples\sample.csv -e participant_email -m examples\sample_email_message.html --attach "examples\sample_attach.txt,examples\sample_attach.txt" --parse zzz###"asasasas" -o examples\output
```
The command above will generates a PDF certificate:
- for each attendant listed in file - examples\sample.csv
- parse certificate HTML file - examples\sample.html
- inform that column name for email is: participant_email
- parse email message from file examples\sample_email_message.html
- it add extra files as attachments: examples\sample_attach.txt, examples\sample_attach.txt
- it add extra placeholder: name: zzz which will be parse to: asasasas
- output folder is: examples\output
- send certificate and extra files to attendant email


## Contributing
Please submit bug reports, suggestions and pull requests to the [GitHub issue tracker](https://github.com/nitro2010/certificate-generator/issues).

# License
Certificate-Generatoris publised under [GNU GENERAL PUBLIC LICENSE v3.0](https://opensource.org/licenses/GPL-3.0).
