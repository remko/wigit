**⚠️ The implementation of this project is very naive (using processes etc.), and has not gone through any security review. I would be surprised if it still works, and if it does, I wouldn't trust it if I were you. Leaving it here for historical purpose only.**

# [WiGit: A Git-based Wiki](https://el-tramo.be/wigit)

## About

WiGit is a simple Wiki written in PHP, using [Git](http://git.or.cz/) as 
a backend for tracking changes. Besides Git, this wiki makes use of 
[Textile](http://textile.sitemonks.com) for marking up text. 


## Features

- Very simple and light
- Easily customizable using themes
- Extensive syntax for marking up text (using Textile)
- Full history tracking support
- Basic support for users/authors, by using the HTTP authentication headers 
  to extract the user.
- Support for pretty URLs (using URL rewriting)


## Requirements

- Webserver
- PHP4
- Git


## Installation

- Put the WiGit dir in some place where the webserver can find it
- Make sure there's a 'data' subdir, and that it is writable by the webserver
- Copy config.php.sample to config.php, and edit config.php to reflect your 
  local settings
- Surf to the wigit URL, and you should start by editing the front page

For URL rewriting, change the `SCRIPT_URL` to be the base URL prefix (as 
is shown in the config file), and add the necessary URL rewrite rules for
your webserver. E.g.,

- For Apache, add the following to .htaccess in your wigit install dir:

		<IfModule mod_rewrite.c>
			RewriteEngine On
			RewriteBase /wigit/
			RewriteCond %{REQUEST_FILENAME} !-f
			RewriteRule (.*) /wigit/index.php?r=/$1 [L] 
		</IfModule>

- For lighttpd, add the following to your config file:

		url.rewrite-once = (
				"^/wigit/themes/(.*)" => "$0",
				"^/wigit(.*)" => "/wigit/index.php?r=$1",
			)

(where `/wigit` is replaced by your own base url)

For user support, configure your webserver to require authentication for
the wigit install dir. E.g.

- For Apache, add the following to .htaccess in your wigit install dir:

		AuthType Basic
		AuthName "My WiGit"
		AuthUserFile /path/to/passwords/file
		Require valid-user

- For lighttpd, add the following to your config file:

		auth.backend = "htdigest"
		auth.backend.htdigest.userfile = "/path/to/htdigest/file"
		auth.require = (
					"/wigit" => (
							"method" => "digest",
							"realm" => "My WiGit",
							"require" => "valid-user",
						)
				)
