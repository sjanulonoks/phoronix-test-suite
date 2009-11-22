<?php

/*
	Phoronix Test Suite
	URLs: http://www.phoronix.com, http://www.phoronix-test-suite.com/
	Copyright (C) 2008 - 2009, Phoronix Media
	Copyright (C) 2008 - 2009, Michael Larabel
	system_monitor.php: System sensor monitoring module for PTS

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

class system_monitor extends pts_module_interface
{
	const module_name = "System Monitor";
	const module_version = "1.9.9";
	const module_description = "This module contains sensor monitoring support.";
	const module_author = "Michael Larabel";

	static $to_monitor = array();

	public static function module_info()
	{
		$info = "";

		$info .= "\nMonitoring these sensors are as easy as running your normal Phoronix Test Suite commands but at the beginning of the command add: MONITOR=<selected sensors> (example: MONITOR=cpu.temp,cpu.voltage phoronix-test-suite benchmark universe). Below are all of the sensors supported by this version of the Phoronix Test Suite.\n\n";
		$info .= "Supported Options:\n";
		foreach(self::monitor_arguments() as $arg)
		{
			$info .= "  - " . $arg . "\n";
		}

		return $info;
	}

	//
	// General Functions
	//

	public static function __pre_run_process()
	{
		self::$to_monitor = array();
		$to_show = explode(",", getenv("MONITOR"));
		$monitor_all = in_array("all", $to_show);

		foreach(pts_available_sensors() as $pts_sensor)
		{
			if($monitor_all || in_array($pts_sensor->get_identifier(), $to_show) || in_array("all." . $pts_sensor->get_sensor_type(), $to_show))
			{
				if($pts_sensor->read_sensor() != -1)
				{
					// Sensor supported
					array_push(self::$to_monitor, $pts_sensor);
					pts_module::save_file("logs/" . $pts_sensor->get_identifier());
				}
			}
		}

		pts_module::pts_timed_function(9, "pts_monitor_update");
	}
	public static function __event_results_process(&$tandem_xml)
	{
		// Elapsed time

		$device = array();
		$type = array();
		$unit = array();
		$m_array = array();
		$type_index = array();

		foreach(self::$to_monitor as $pts_sensor)
		{
			$sensor_results = self::parse_monitor_log("logs/" . $pts_sensor->get_identifier());

			if(count($sensor_results) > 0)
			{
				if(!isset($type_index[$pts_sensor->get_sensor_string()]))
				{
					$type_index[$pts_sensor->get_sensor_string()] = array();
				}

				array_push($device, $pts_sensor->get_formatted_hardware_type());
				array_push($type, $pts_sensor->get_sensor_string());
				array_push($unit, $pts_sensor->get_sensor_unit());
				array_push($m_array, $sensor_results);
				array_push($type_index[$pts_sensor->get_sensor_string()], count($m_array) - 1);
			}
		}

		$info_report = "";
		if(isset($m_array[0]) && count($m_array[0]) == 1)
		{
			$info_report .= "Current Sensor Readings:\n\n";
			for($i = 0; $i < count($m_array); $i++)
			{
				$info_report .= $device[$i] . " " . $type[$i] . " Monitor: " . $m_array[$i][0] . " " .  $unit[$i];

				if($i < (count($m_array) - 1))
					$info_report .= "\n";
			}
		}
		else
		{
			for($i = 0; $i < count($m_array); $i++)
			{
				// Calculate statistics
				if($i > 0)
					$info_report .= "\n\n";

				$low = false;
				$high = 0;
				$total = 0;

				foreach($m_array[$i] as $temp)
				{
					if($low == false)
						$low = $temp;

					if($temp < $low || ($low == 0 && $type[$i] <> "Usage"))
						$low = $temp;
					else if($temp > $high)
						$high = $temp;

					$total += $temp;
				}
				$avg = $total / count($m_array[$i]);

				$info_report .= $device[$i] . " " . $type[$i] . " Statistics:\n\nLow: " . pts_trim_double($low) . ' ' . $unit[$i] . "\nHigh: " . pts_trim_double($high) . ' ' . $unit[$i] . "\nAverage: " . pts_trim_double($avg) . ' ' . $unit[$i];
			}

			if(trim($info_report) != null)
			{
				foreach($type_index as $key => $sub_array)
				{
					if(count($sub_array) > 0)
					{
						$time_minutes = floor(pts_time_elapsed() / 60);

						if($time_minutes == 0)
							$time_minutes = 1;

						$graph_title = $type[$sub_array[0]] . " Monitor";
						$graph_unit = $unit[$sub_array[0]];
						$graph_unit = str_replace("°C", "Celsius", $graph_unit);
						$sub_title = "Elapsed Time: " . $time_minutes . " Minutes - ";
						$sub_title .= implode(" ", pts_read_assignment("TO_RUN_IDENTIFIERS"));


						$tandem_id = pts_request_new_id();
						$tandem_xml->addXmlObject(P_RESULTS_TEST_TITLE, $tandem_id, $graph_title);
						$tandem_xml->addXmlObject(P_RESULTS_TEST_VERSION, $tandem_id, null);
						$tandem_xml->addXmlObject(P_RESULTS_TEST_ATTRIBUTES, $tandem_id, $sub_title);
						$tandem_xml->addXmlObject(P_RESULTS_TEST_SCALE, $tandem_id, $graph_unit);
						$tandem_xml->addXmlObject(P_RESULTS_TEST_PROPORTION, $tandem_id, null);
						$tandem_xml->addXmlObject(P_RESULTS_TEST_RESULTFORMAT, $tandem_id, "LINE_GRAPH");
						$tandem_xml->addXmlObject(P_RESULTS_TEST_TESTNAME, $tandem_id, null);
						$tandem_xml->addXmlObject(P_RESULTS_TEST_ARGUMENTS, $tandem_id, $type[$sub_array[0]]);

						// TODO: Merging support
						foreach($sub_array as $id_point)
						{
							$tandem_xml->addXmlObject(P_RESULTS_RESULTS_GROUP_IDENTIFIER, $tandem_id, pts_read_assignment("TEST_RESULTS_IDENTIFIER") . " - " . $device[$id_point], 5, "sys-monitor-" . $id_point);
							$tandem_xml->addXmlObject(P_RESULTS_RESULTS_GROUP_VALUE, $tandem_id, implode(",", $m_array[$id_point]), 5, "sys-monitor-" . $id_point);
							$tandem_xml->addXmlObject(P_RESULTS_RESULTS_GROUP_RAW, $tandem_id, implode(",", $m_array[$id_point]), 5, "sys-monitor-" . $id_point);
						}
					}
				}
			}
		}

		if(count($m_array) > 0)
			$info_report .= "\n\nElapsed Time: " . pts_format_time_string(pts_time_elapsed());

		// terminal output
		if(!empty($info_report))
			echo pts_string_header($info_report);
	}
	public static function pts_monitor_update()
	{
		foreach(self::$to_monitor as $pts_sensor)
		{
			$sensor_value = $pts_sensor->read_sensor();

			if($sensor_value != -1 && pts_module::is_file("logs/" . $pts_sensor->get_identifier()))
			{
				pts_module::save_file("logs/" . $pts_sensor->get_identifier(), $sensor_value, true);
			}
		}
	}
	private function parse_monitor_log($log_file)
	{
		$log_f = pts_module::read_file($log_file);
		pts_module::remove_file($log_file);
		$line_breaks = explode("\n", $log_f);
		$results = array();

		foreach($line_breaks as $line)
		{
			$line = trim($line);
			if(!empty($line))
				array_push($results, $line);
		}

		return $results;
	}
	private function monitor_arguments()
	{
		$args = array("all");

		foreach(pts_available_sensors() as $pts_sensor)
		{
			if(!in_array("all." . $pts_sensor->get_sensor_type(), $args))
			{
				array_push($args, "all." . $pts_sensor->get_sensor_type());
			}

			array_push($args, $pts_sensor->get_hardware_type() . "." . $pts_sensor->get_sensor_type());
		}

		return $args;
	}
}

?>
