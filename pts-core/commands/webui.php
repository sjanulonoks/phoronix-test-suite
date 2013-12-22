<?php

/*
	Phoronix Test Suite
	URLs: http://www.phoronix.com, http://www.phoronix-test-suite.com/
	Copyright (C) 2012 - 2013, Phoronix Media
	Copyright (C) 2012 - 2013, Michael Larabel

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

class webui implements pts_option_interface
{
	const doc_skip = true; // TODO XXX: cleanup this code before formally advertising this...
	const doc_section = 'Web User Interface';
	const doc_description = 'Launch the Phoronix Test Suite web user-interface.';

	public static function run($r)
	{
		pts_file_io::unlink(PTS_USER_PATH . 'web-server-launcher');
		if(PHP_VERSION_ID < 50400)
		{
			echo 'Running an unsupported PHP version. PHP 5.4+ is required to use this feature.' . PHP_EOL . PHP_EOL;
			return false;
		}

		$server_launcher = '#!/bin/sh' . PHP_EOL . getenv('PHP_BIN');
		$web_port = 0;
		$remote_access = pts_config::read_user_config('PhoronixTestSuite/Options/Server/RemoteAccessAllowed', 'FALSE');
		$remote_access = is_numeric($remote_access) && $remote_access > 1 ? $remote_access : false;

		if($remote_access)
		{
			// ALLOWING SERVER TO BE REMOTELY ACCESSIBLE
			$server_ip = '0.0.0.0';
			$fp = false;
			$errno = null;
			$errstr = null;

			if(($fp = fsockopen('127.0.0.1', $remote_access, $errno, $errstr, 5)) != false)
			{
				trigger_error('Port ' . $remote_access . ' is already in use by another server process. Close that process or change the Phoronix Test Suite server port via ~/.phoronix-test-suite/user-config.xml to proceed.', E_USER_ERROR);
				fclose($fp);
				return false;
			}
			else
			{
				$web_port = $remote_access;
			}
		}
		else
		{
			// SERVER JUST RUNNING FOR LOCAL SYSTEM, SO ALSO COME UP WITH RANDOM FREE PORT
			$server_ip = 'localhost';
			// Randomly choose a port and ensure it's not being used...
			$fp = false;
			$errno = null;
			$errstr = null;
			do
			{
				if($fp != false)
				{
					fclose($fp);
				}

				$web_port = rand(2000, 9999);
			}
			while(($fp = fsockopen('127.0.0.1', $web_port, $errno, $errstr, 5)) != false);

		}

		if(strpos(getenv('PHP_BIN'), 'hhvm'))
		{
			$server_launcher .= ' -m server -vServer.Type=fastcgi -vServer.Port=' . $web_port . ' -vServer.IP=' . $server_ip . ' -t ' . PTS_CORE_PATH . 'web-interface/ &' . PHP_EOL;
		}
		else
		{
			$server_launcher .= ' -S ' . $server_ip . ':' . $web_port . ' -t ' . PTS_CORE_PATH . 'web-interface/ &' . PHP_EOL;
		}
		$server_launcher .= 'server_pid=$!'. PHP_EOL . PHP_EOL;

		if(($browser = pts_client::executable_in_path('chromium-browser')) || ($browser = pts_client::executable_in_path('google-chrome')))
		{
			// chromium-browser --incognito --temp-profile --kiosk --app=
			$server_launcher .= 'echo "Launching Browser"' . PHP_EOL;
			$server_launcher .= $browser . ' --temp-profile --app=http://localhost:' . $web_port . ' -start-maximized';
			// chromium-browser --kiosk URL starts full-screen
		}

		$server_launcher .= PHP_EOL . 'kill $server_pid';
		file_put_contents(PTS_USER_PATH . 'web-server-launcher', $server_launcher);
	}
}

?>
