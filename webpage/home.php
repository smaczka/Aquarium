<?php
	session_start();
	
	if (!isset($_SESSION['logged']))
	{
		header('Location: index.php');
		exit();
	}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>Akwarium</title>
	<link rel="icon" href="images/ico.png">
	<link rel="stylesheet" href="index.css" type="text/css" media="screen" />
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	
	<script type="text/javascript" src="lib/jquery-3.1.1.js" charset="utf-8"></script>
	<script type="text/javascript" src="lib/jquery-ui/jquery-ui.js" charset="utf-8"></script>
	<link href="lib/jquery-ui/jquery-ui.css" rel="stylesheet">
	
	<link rel="stylesheet" type="text/css" href="lib/datetimepicker/jquery.datetimepicker.css"/>
	<script src="lib/datetimepicker/build/jquery.datetimepicker.full.js"></script>
	
	<script src="lib/amcharts/amcharts.js" type="text/javascript"></script>
	<script src="lib/amcharts/serial.js" type="text/javascript"></script>
	<link rel="stylesheet" href="lib/amcharts/style_chart.css" type="text/css">
	<script>
		var temperature_chart, device_chart, temperature_chart_legend, device_chart_legend;
		var local_measures;
		var chart_temperature_json, chart_device_json, scheduler_data_json;
		var local_devices;
		var last_object;
		var control_choose_period, temperature_choose_period;
		
		//get last state
		function get_state(){
			$.ajax({
				'timeout': 10000,
				'cache': false,
				'async': false,
				'global': false,
				'url': "backend/json_resp.php?action=get_state&note=web_page",
				'dataType': "json",
				'success': function(state_data) {
					get_state_json = state_data;
				},
			})
			
			//fill last state table
			$("#table_last_control").text(get_state_json['timestamp'][0]);
			$("#table_ip").text(get_state_json['ip'][0]);
			$("#table_source").text(get_state_json['note'][0]);

			//set main control button state and apparence
			if (get_state_json['state'][0] == '0'){
				$("#table_last_state").text('Włączony');
				$("#control_button").text('Wyłącz');
				
				$("#control_button").css("border", "3px solid #8F2929");
				$("#control_button").css("text-shadow", "0px 1px 0px #8A3C3C");
				
				$("#control_button").css("-moz-box-shadow", "0px 10px 14px -7px #732727");
				$("#control_button").css("-webkit-box-shadow", "0px 10px 14px -7px #732727");
				$("#control_button").css("box-shadow", "0px 10px 14px -7px #732727");
				
				$("#control_button").css("background", "-moz-linear-gradient(top, #6B3E3E 5%, #B00A0A 100%)");
				$("#control_button").css("background", "-webkit-gradient(linear, left top, left bottom, color-stop(0.05, #6B3E3E), color-stop(1, #B00A0A))");
				$("#control_button").css("background", "-webkit-linear-gradient(top, #6B3E3E 5%, #B00A0A 100%)");
				$("#control_button").css("background", "-o-linear-gradient(top, #6B3E3E 5%, #B00A0A 100%)");
				$("#control_button").css("background", "-ms-linear-gradient(top, #6B3E3E 5%, #B00A0A 100%)");
			}else if (get_state_json['state'][0] == '1'){
				$("#table_last_state").text('Wyłączony');
				$("#control_button").text('Włącz');
				
				$("#control_button").css("border", "3px solid #4b8f29");
				$("#control_button").css("text-shadow", "0px 1px 0px #5b8a3c");
				
				$("#control_button").css("-moz-box-shadow", "0px 10px 14px -7px #3e7327");
				$("#control_button").css("-webkit-box-shadow", "0px 10px 14px -7px #3e7327");
				$("#control_button").css("box-shadow", "0px 10px 14px -7px #3e7327");
				
				$("#control_button").css("background", "-moz-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
				$("#control_button").css("background", "-webkit-gradient(linear, left top, left bottom, color-stop(0.05, #4d6b3e), color-stop(1, #42b00a))");
				$("#control_button").css("background", "-webkit-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
				$("#control_button").css("background", "-o-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
				$("#control_button").css("background", "-ms-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
			}
		}
		
		//get temperature data for chart
		function temperature_chart_foo(period){
			var temperature_chart_values = [];
			var grap1_visible = 1, grap2_visible = 1;
			
			//set default chart time window
			if ((period == undefined) || (period == '')){
				period = "last_day";
			} else if (period == 'choose_period'){
				period = temperature_choose_period;
			}
			
			//get json temperature data
			temperature_chart_data();

			//set temperature chart options
			temperature_chart = new AmCharts.AmSerialChart();
			temperature_chart.dataProvider = temperature_chart_values;
			temperature_chart.categoryField = "date";
			temperature_chart.dataDateFormat = "YYYY-MM-DD JJ:NN:SS";
			temperature_chart.balloon.bulletSize = 1;
			temperature_chart.backgroundColor = "#f2f2f2";
			temperature_chart.backgroundAlpha = 1;
			temperature_chart.language = "pl";

			//set x axis options
			var category_axis = temperature_chart.categoryAxis;
			category_axis.parseDates = true;
			category_axis.minPeriod = "mm";
			category_axis.autoGridCount = false;
			category_axis.gridCount = 50;
			category_axis.gridAlpha = 0;
			category_axis.gridColor = "#000000";
			category_axis.axisColor = "#555555";
			category_axis.axisColor = "#DADADA";
			category_axis.labelRotation = 45;
			category_axis.showFirstLabel = true;
			category_axis.showLastLabel = true;
			category_axis.dateFormats = [{
				period: 'fff',
				format: 'JJ:NN:SS'
			}, {
				period: 'ss',
				format: 'JJ:NN:SS'
			}, {
				period: 'mm',
				format: 'JJ:NN:SS'
			}, {
				period: 'hh',
				format: 'JJ:NN:SS'
			}, {
				period: 'DD',
				format: 'DD-MM-YYYY'
			}, {
				period: 'WW',
				format: 'DD'
			}, {
				period: 'MM',
				format: ''
			}, {
				period: 'YYYY',
				format: 'YYYY'
			}];

			//set y axis options
			var values_axis = new AmCharts.ValueAxis();

			var temperature_max = 0, temperature_min = 99;

			for (var i=0; i< chart_temperature_json['temperature_1'].length-6; i++) {
				if (chart_temperature_json['temperature_1'][i] < temperature_min){
					temperature_min = chart_temperature_json['temperature_1'][i];
				}

				if (chart_temperature_json['temperature_1'][i] > temperature_max){
					temperature_max = chart_temperature_json['temperature_1'][i];
				}
			}

			values_axis.maximum = parseInt(Math.ceil(temperature_max) + 2);
			values_axis.minimum = parseInt(Math.floor(temperature_min) - 1);
			values_axis.axisAlpha = 0;
			values_axis.dashLength = 1;
			temperature_chart.addValueAxis(values_axis);

			//set temperature graph1
			var values_graph1 = new AmCharts.AmGraph();
			values_graph1.title = "temperature_1";
			values_graph1.valueField = "temperature #1";
			values_graph1.type = "smoothedLine";
			values_graph1.bullet = "round";
			values_graph1.bulletBorderColor = "#FFFFFF";
			values_graph1.bulletBorderThickness = 2;
			values_graph1.bulletBorderAlpha = 0.5;
			values_graph1.lineThickness = 2;
			values_graph1.lineColor = "#EB2700";
			values_graph1.negativeLineColor = "#EB2700";
			values_graph1.hideBulletsCount = 80;
			temperature_chart.addGraph(values_graph1);

			//set temperature graph2
			var values_graph2 = new AmCharts.AmGraph();
			values_graph2.title = "temperature_2";
			values_graph2.valueField = "temperature #2";
			values_graph2.type = "smoothedLine";
			values_graph2.bullet = "round";
			values_graph2.bulletBorderColor = "#FFFFFF";
			values_graph2.bulletBorderThickness = 2;
			values_graph2.bulletBorderAlpha = 1;
			values_graph2.lineThickness = 2;
			values_graph2.lineColor = "#EB9100";
			values_graph2.negativeLineColor = "#EB9100";
			values_graph2.hideBulletsCount = 80;
			temperature_chart.addGraph(values_graph2);

			//set temperature chart cursor
			var temperature_chart_cursor = new AmCharts.ChartCursor();
			temperature_chart_cursor.cursorPosition = "mouse";
			temperature_chart_cursor.pan = true;
			temperature_chart.addChartCursor(temperature_chart_cursor);

			//set temperature chart legend
			temperature_chart_legend = new AmCharts.AmLegend();
			temperature_chart_legend.align = "center";
			temperature_chart_legend.valueWidth = 100;
			temperature_chart_legend.valueAlign = "left";
			temperature_chart_legend.equalWidths = false;
			temperature_chart_legend.data = [{title:"Temperatura #1", color:"#EB2700"}, {title:"Temperatura #2", color:"#EB9100"}];
			temperature_chart_legend.periodValueText = "[[Włączony]]";
			temperature_chart_legend.labelText = "[[title]]";
			temperature_chart_legend.textClickEnabled = false;
			temperature_chart.addLegend(temperature_chart_legend);

			//set temperature chart scroll bar
			var temperature_chart_scroll_bar = new AmCharts.ChartScrollbar();
			temperature_chart.addChartScrollbar(temperature_chart_scroll_bar);

			//set chart credits position
			temperature_chart.creditsPosition = "bottom-right";

			//write temperature chart
			temperature_chart.write("temperature_chart");

			//show/hide temperature series
			if ($('#temperature_serie_show_temperature_1').prop('checked')){
				for (i=0; i<2; i++){
					if (temperature_chart_legend.data[i]['title'] == 'Temperatura #1'){
						temperature_chart_legend.data[i]['color'] ='#C4BEBE';
						temperature_chart.hideGraph(temperature_chart.graphs[i]);
					}
				}
			} else {
				for (i=0; i<2; i++){
					if (temperature_chart_legend.data[i]['title'] == 'Temperatura #1'){
						temperature_chart_legend.data[i]['color'] ='#EB2700';
						temperature_chart.showGraph(temperature_chart.graphs[i]);
					}
				}
			}

			if ($('#temperature_serie_show_temperature_2').prop('checked')){
				for (i=0; i<2; i++){
					if (temperature_chart_legend.data[i]['title'] == 'Temperatura #2'){
						temperature_chart_legend.data[i]['color'] ='#C4BEBE';
						temperature_chart.hideGraph(temperature_chart.graphs[i]);
					}
				}
			} else {
				for (i=0; i<2; i++){
					if (temperature_chart_legend.data[i]['title'] == 'Temperatura #2'){
						temperature_chart_legend.data[i]['color'] ='#EB9100';
						temperature_chart.showGraph(temperature_chart.graphs[i]);
					}
				}
			}

			//get json temperature data
			function temperature_chart_data(){
				//send ajax request
				$.ajax({
					'timeout': 10000,
					'cache': false,
					'async': false,
					'global': false,
					'url': "backend/json_resp.php?action=get_temperature&period="+period,
					'dataType': "json",
					'success': function(temperature_data) {
						chart_temperature_json = temperature_data;
					},
				})

				//fill table temperature cells
				$("#table_last_temperature1").text(chart_temperature_json['temperature_1'][chart_temperature_json['temperature_1'].length-7] + ' °C');
				$("#table_last_temperature2").text(chart_temperature_json['temperature_2'][chart_temperature_json['temperature_2'].length-1] + ' °C');
				$("#table_last_temperature_timestamp").text(chart_temperature_json['timestamp'][chart_temperature_json['temperature_2'].length-2]);

				$("#temperature1_value").text(chart_temperature_json['temperature_1'][chart_temperature_json['temperature_1'].length-7] + ' °C');
				$("#temperature2_value").text(chart_temperature_json['temperature_2'][chart_temperature_json['temperature_2'].length-1] + ' °C');

				$("#table_avg_temperature1").text(chart_temperature_json['temperature_1'][chart_temperature_json['temperature_1'].length-6] + ' °C');
				$("#table_avg_temperature2").text(chart_temperature_json['temperature_1'][chart_temperature_json['temperature_1'].length-5] + ' °C');

				$("#table_max_temperature1").text(chart_temperature_json['temperature_1'][chart_temperature_json['temperature_1'].length-4] + ' °C');
				$("#table_max_temperature2").text(chart_temperature_json['temperature_1'][chart_temperature_json['temperature_1'].length-3] + ' °C');

				$("#table_min_temperature1").text(chart_temperature_json['temperature_1'][chart_temperature_json['temperature_1'].length-2] + ' °C');
				$("#table_min_temperature2").text(chart_temperature_json['temperature_1'][chart_temperature_json['temperature_1'].length-1] + ' °C');

				for (var i = 0; i < chart_temperature_json['temperature_1'].length-6; i++) {
					temperature_chart_values.push({
						date: chart_temperature_json['timestamp'][i],
						'temperature #1': chart_temperature_json['temperature_1'][i],
						'temperature #2': chart_temperature_json['temperature_2'][i]
					});
				}
			}
		}

		//get device state data for chart
		function device_chart_foo(period){
			var device_chart_values = [];

			//set default chart time window
			if (period == undefined){
				period = "last_day";
			}else if (period == 'choose_period'){
				period = control_choose_period;
			}

			//get json device state data
			device_chart_data();

			//set device chart options
			device_chart = new AmCharts.AmSerialChart();
			device_chart.dataProvider = device_chart_values;
			device_chart.categoryField = "date";
			device_chart.dataDateFormat = "YYYY-MM-DD JJ:NN:SS";
			device_chart.language = "pl";

			var balloon = device_chart.balloon;
			balloon.cornerRadius = 10;
			balloon.adjustBorderColor = false;
			balloon.horizontalPadding = 10;
			balloon.verticalPadding = 10;

			//set x axis options
			var category_axis = device_chart.categoryAxis;
			category_axis.parseDates = true;
			category_axis.minPeriod = "mm";
			category_axis.autoGridCount = false;
			category_axis.gridCount = 50;
			category_axis.gridAlpha = 0;
			category_axis.gridColor = "#000000";
			category_axis.axisColor = "#555555";
			category_axis.labelRotation = 45;
			category_axis.showFirstLabel = true;
			category_axis.showLastLabel = true;
			category_axis.dateFormats = [{
				period: 'fff',
				format: 'JJ:NN:SS'
			}, {
				period: 'ss',
				format: 'JJ:NN:SS'
			}, {
				period: 'mm',
				format: 'JJ:NN:SS'
			}, {
				period: 'hh',
				format: 'JJ:NN:SS'
			}, {
				period: 'DD',
				format: 'DD-MM-YYYY'
			}, {
				period: 'WW',
				format: 'DD'
			}, {
				period: 'MM',
				format: 'MMM'
			}, {
				period: 'YYYY',
				format: 'YYYY'
			}];

			//set y axis options
			var values_axis = new AmCharts.ValueAxis();
			values_axis.gridAlpha = 0.05;
			values_axis.axisAlpha = 1;
			values_axis.integersOnly = true;
			values_axis.maximum = 1;
			values_axis.showFirstLabel = false;
			values_axis.showLastLabel = false;
			device_chart.addValueAxis(values_axis);

			//set device graph1
			var values_graph1 = new AmCharts.AmGraph();
			values_graph1.title = "Włączony";
			values_graph1.valueField = "Włączony";
			values_graph1.type = "line";
			values_graph1.valueAxis = values_axis;
			values_graph1.lineColorField = "lineColor";
			values_graph1.fillColorsField = "lineColor";
			values_graph1.fillAlphas = 1;
			values_graph1.balloonText = "[[value]]";
			values_graph1.lineThickness = 0;
			values_graph1.legendValueText = "[[value]]";
			device_chart.addGraph(values_graph1);

			//set device graph2
			var values_graph2 = new AmCharts.AmGraph();
			values_graph2 = new AmCharts.AmGraph();
			values_graph2.title = 'Wyłączony';
			values_graph2.valueField = "Wyłączony";
			values_graph2.type = "line";
			values_graph2.valueAxis = values_axis;
			values_graph2.lineColorField = "lineColor";
			values_graph2.fillColorsField = "lineColor";
			values_graph2.fillAlphas = 1;
			values_graph2.balloonText = "[[value]]";
			values_graph2.lineThickness = 0;
			values_graph2.legendValueText = "[[value]]";
			device_chart.addGraph(values_graph2);

			//set device chart cursor
			var device_chart_cursor = new AmCharts.ChartCursor();
			device_chart_cursor.zoomable = true;
			device_chart_cursor.categoryBalloonDateFormat = "YYYY-MM-DD JJ:NN:SS";
			device_chart_cursor.cursorAlpha = 0;
			device_chart.addChartCursor(device_chart_cursor);

			//set device chart legend
			device_chart_legend = new AmCharts.AmLegend();
			device_chart_legend.align = "center";
			device_chart_legend.valueWidth = 100;
			device_chart_legend.valueAlign = "left";
			device_chart_legend.equalWidths = false;
			device_chart_legend.data = [{title:"Wyłączony", color:"#CC0000"}, {title:"Włączony", color:"#00CC00"}]
			device_chart_legend.periodValueText = "[[Włączony]]";
			device_chart_legend.labelText = "[[title]]";
			device_chart.addLegend(device_chart_legend);

			var device_chart_scroll_bar = new AmCharts.ChartScrollbar();
			device_chart.addChartScrollbar(device_chart_scroll_bar);

			//write device chart
			device_chart.write("control_chart");

			//get json device state data
			function device_chart_data(){
				$.ajax({
					'timeout': 10000,
					'cache': false,
					'async': false,
					'global': false,
					'url': "backend/json_resp.php?action=get_device&period="+period,
					'dataType': "json",
					'success': function(device_state_data) {
						chart_device_json = device_state_data;
					},
				})

				for (i = 0; i<chart_device_json['state'].length; i++){
					if (chart_device_json['state'][i] == '0')
						device_chart_values.push({'lineColor': 'red', 'date': chart_device_json['timestamp'][i], 'Włączony': '500', 'Wyłączony': '0'});
					else if (chart_device_json['state'][i] == '1')
						device_chart_values.push({'lineColor': 'green', 'date': chart_device_json['timestamp'][i], 'Włączony': '500', 'Wyłączony': '0'});
				}
			}
		}
		
		//get scheduler
		function get_scheduler(){
			$.ajax({
				'timeout': 10000,
				'cache': false,
				'async': false,
				'global': false,
				'url': "backend/json_resp.php?action=get_scheduler",
				'dataType': "json",
				'success': function(scheduler_data) {
					scheduler_data_json = scheduler_data;
				},
			})
			
			//clear lists
			$('#scheduler_list').find('option').remove().end();
			
			//add values to lists
			for (i = 0; i<scheduler_data_json['list_name'].length; i++){
				$('#scheduler_list').append($('<option>', {
					value: scheduler_data_json['id'][i],
					text: scheduler_data_json['list_name'][i]
				}));
				
				if (scheduler_data_json['device_action'][i] == '0'){
					$("#device_action option").filter(function() {
					return $(this).val() === "scheduler_action_off"; 
					}).prop('selected', true);
				}else if (scheduler_data_json['device_action'][i] == '1'){
					$("#device_action option").filter(function() {
					return $(this).val() === "scheduler_action_on"; 
					}).prop('selected', true);
				}
			}
			
			//add value 'add new plan' to list
			$('#scheduler_list').append($('<option>', {
				value: parseInt(scheduler_data_json['list_name'].length),
				text: "Dodaj nowy plan..."
			}));
			
			//set selected first item on list
			$('#scheduler_list :nth-child(1)').prop('selected', true);
			
			//set apparence to scheduler button
			$("#scheduler_button").text('Usun wpis');
			$("#scheduler_button").css("border", "3px solid #8F2929");
			$("#scheduler_button").css("text-shadow", "0px 1px 0px #8A3C3C");

			$("#scheduler_button").css("-moz-box-shadow", "0px 10px 14px -7px #732727");
			$("#scheduler_button").css("-webkit-box-shadow", "0px 10px 14px -7px #732727");
			$("#scheduler_button").css("box-shadow", "0px 10px 14px -7px #732727");

			$("#scheduler_button").css("background", "-moz-linear-gradient(top, #6B3E3E 5%, #B00A0A 100%)");
			$("#scheduler_button").css("background", "-webkit-gradient(linear, left top, left bottom, color-stop(0.05, #6B3E3E), color-stop(1, #B00A0A))");
			$("#scheduler_button").css("background", "-webkit-linear-gradient(top, #6B3E3E 5%, #B00A0A 100%)");
			$("#scheduler_button").css("background", "-o-linear-gradient(top, #6B3E3E 5%, #B00A0A 100%)");
			$("#scheduler_button").css("background", "-ms-linear-gradient(top, #6B3E3E 5%, #B00A0A 100%)");

			$("#scheduler_name").val(scheduler_data_json['list_name'][0]);
			
			var pattern_cycle = /[0-1]{7}\s[0-9]{1,2}:[0-9]{1,2}:[0-9]{1,2}/;
			var pattern_once = /[0-9]{4}-[0-9]{2}-[0-9]{2}\s[0-9]{1,2}:[0-9]{1,2}:[0-9]{1,2}/;
			
			//check type of first list row (cycle or once)
			if (pattern_once.test(scheduler_data_json['datetime'][0])){
				$(".days").hide();
				$(".days_slider").hide();
				$("#task_checked").hide();
				
				$("#scheduler_input_datetime").val(scheduler_data_json['datetime'][0]);
				$("#device_action_type option").filter(function() {
					return $(this).val() === "scheduler_once"; 
				}).prop('selected', true);
				
				var temperature_period_date = new Date();

				temperature_period_month_temp = parseInt(temperature_period_date.getUTCMonth() + 1);
				temperature_period_date_temp = temperature_period_date.getDate();
				temperature_period_hours_temp = temperature_period_date.getHours();
				temperature_period_minutes_temp = temperature_period_date.getMinutes();
				temperature_period_seconds_temp = temperature_period_date.getSeconds();

				if (temperature_period_month_temp < 10)
					temperature_period_month_temp = String('0' + temperature_period_month_temp);

				if (temperature_period_date_temp < 10)
					temperature_period_date_temp = String('0' + temperature_period_date_temp);

				var temperature_period_seconds_temp_date = temperature_period_date.getFullYear() + "-" + temperature_period_month_temp + "-" + temperature_period_date_temp;
				
				//add datetime picker control
				$.datetimepicker.setLocale('pl');
				$('#scheduler_input_datetime').datetimepicker({
					dayOfWeekStart : 1,
					format:'Y-m-d H:i:s',
					lang:'pl',
					startDate:	temperature_period_seconds_temp_date
				});
			}else if (pattern_cycle.test(scheduler_data_json['datetime'][0])){
				$(".days").show();
				$(".days_slider").show();
				
				$("#scheduler_input_datetime").val(scheduler_data_json['datetime'][0].substring(8));
				$("#device_action_type option").filter(function() {
					return $(this).val() == "scheduler_cycle"; 
				}).prop('selected', true);
				
				//add datetime picker control
				$.datetimepicker.setLocale('pl');
				$('#scheduler_input_datetime').datetimepicker({
					datepicker:false,
					format:'H:i:s',
					step:30
				});
				
				//set property checked for days radio button
				for (var j = 0; j<7; j++){
					if ($('#days_0' + j).val() == j){
						if (scheduler_data_json['datetime'][i][j] == '1'){
							$('#days_0' + j).prop('checked', true);
						}else if ((scheduler_data_json['datetime'][i][j] == '0')){
							$('#days_0' + j).prop('checked', false);
						}
					}
				}
			}
		}
		
		//send change state request to backend control file
		function change_state(){
			$.ajax({
				'timeout': 10000,
				'cache': false,
				'async': false,
				'global': false,
				'url': "backend/json_resp.php?action=change_state&note=web_page",
				'dataType': "json",
				'success': function(change_state_data) {
					change_state_json = change_state_data;
				}
			});

			//decode returned value and change apparence of control button
			if (change_state_json['change_state'][0] == '4'){
				$("#table_last_control").text(change_state_json['timestamp'][0]);
				$("#table_ip").text(change_state_json['ip'][0]);
				$("#table_source").text(change_state_json['note'][0]);
				
				if (change_state_json['state'][0] == '0'){
					$("#table_last_state").text('Włączony');
					$("#control_button").text('Wyłącz');
					
					$("#control_button").css("border", "3px solid #8F2929");
					$("#control_button").css("text-shadow", "0px 1px 0px #8A3C3C");
					
					$("#control_button").css("-moz-box-shadow", "0px 10px 14px -7px #732727");
					$("#control_button").css("-webkit-box-shadow", "0px 10px 14px -7px #732727");
					$("#control_button").css("box-shadow", "0px 10px 14px -7px #732727");
					
					$("#control_button").css("background", "-moz-linear-gradient(top, #6B3E3E 5%, #B00A0A 100%)");
					$("#control_button").css("background", "-webkit-gradient(linear, left top, left bottom, color-stop(0.05, #6B3E3E), color-stop(1, #B00A0A))");
					$("#control_button").css("background", "-webkit-linear-gradient(top, #6B3E3E 5%, #B00A0A 100%)");
					$("#control_button").css("background", "-o-linear-gradient(top, #6B3E3E 5%, #B00A0A 100%)");
					$("#control_button").css("background", "-ms-linear-gradient(top, #6B3E3E 5%, #B00A0A 100%)");
				}else if (change_state_json['state'][0] == '1'){
					$("#table_last_state").text('Wyłączony');
					$("#control_button").text('Włącz');
					
					$("#control_button").css("border", "3px solid #4b8f29");
					$("#control_button").css("text-shadow", "0px 1px 0px #5b8a3c");
					
					$("#control_button").css("-moz-box-shadow", "0px 10px 14px -7px #3e7327");
					$("#control_button").css("-webkit-box-shadow", "0px 10px 14px -7px #3e7327");
					$("#control_button").css("box-shadow", "0px 10px 14px -7px #3e7327");
					
					$("#control_button").css("background", "-moz-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
					$("#control_button").css("background", "-webkit-gradient(linear, left top, left bottom, color-stop(0.05, #4d6b3e), color-stop(1, #42b00a))");
					$("#control_button").css("background", "-webkit-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
					$("#control_button").css("background", "-o-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
					$("#control_button").css("background", "-ms-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
				}
			}else{
				if (change_state_json['change_state'][0] === '1'){
					alert('Nowy wiersz w bazie nie został dodany! Urządzenie jest prawdopodobnie odłączone!');
				} else if (change_state_json['change_state'][0] === '2'){
					alert('Nie mogę utworzyć gniazda sieciowego!');
				} else if (change_state_json['change_state'][0] === '3'){
					alert('Sprawdź serwer bazy danych! Błąd SQL: ' + change_state_data['change_state_sql_error'][0]);
				}
			}
		}

		$(function(){
			var scheduler_input_datetime_before, scheduler_input_datetime_after, scheduler_name_after, scheduler_name_before;
			
			//refresh data in intervals
			state_update = setInterval(
				function(){
					get_state(); 
				}, 2000 
			);
		
			device_chart_update = setInterval(
				function(){
					device_chart_foo($('#control_select_period').val()); 
				}, 10000 
			);
		
			temperature_chart_update = setInterval(
				function(){
					temperature_chart_foo($('#temperature_select_period').val()); 
				}, 10000 
			);
		
			scheduler_update = setInterval(
				function(){
					get_scheduler(); 
				}, 60000 
			);
			
			$("#scheduler_input_datetime").focus(function() {
				scheduler_input_datetime_before = $("#scheduler_input_datetime").val();
			});

			$("#scheduler_input_datetime").focusout(function() {
				scheduler_input_datetime_after = $("#scheduler_input_datetime").val();

				if (scheduler_input_datetime_after !== scheduler_input_datetime_before){
					$("#scheduler_button").text('Zapisz zmiany');
					
					$("#scheduler_button").css("background", "-moz-linear-gradient(top, #994d00 5%, #ff9933 100%)");
					$("#scheduler_button").css("background", "-webkit-gradient(linear, left top, left bottom, color-stop(0.05, #994d00), color-stop(1, #ff9933))");
					$("#scheduler_button").css("background", "-webkit-linear-gradient(top, #994d00 5%, #ff9933 100%)");
					$("#scheduler_button").css("background", "-o-linear-gradient(top, #994d00 5%, #ff9933 100%)");
					$("#scheduler_button").css("background", "-ms-linear-gradient(top, #994d00 5%, #ff9933 100%)");

					$("#scheduler_button").css("border", "3px solid #ff9933");

					$("#scheduler_button").css("-moz-box-shadow", "0px 10px 14px -7px #ff9933");
					$("#scheduler_button").css("-webkit-box-shadow", "0px 10px 14px -7px #ff9933");
					$("#scheduler_button").css("box-shadow", "0px 10px 14px -7px #ff9933");
				}

				if ((scheduler_input_datetime_after !== '') && ($( "#scheduler_list option:selected").text() === 'Dodaj nowy plan...')){
					$("#scheduler_button").text('Dodaj nowy plan');
					
					$("#scheduler_button").css("border", "3px solid #4b8f29");
					$("#scheduler_button").css("text-shadow", "0px 1px 0px #5b8a3c");

					$("#scheduler_button").css("-moz-box-shadow", "0px 10px 14px -7px #3e7327");
					$("#scheduler_button").css("-webkit-box-shadow", "0px 10px 14px -7px #3e7327");
					$("#scheduler_button").css("box-shadow", "0px 10px 14px -7px #3e7327");

					$("#scheduler_button").css("background", "-moz-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
					$("#scheduler_button").css("background", "-webkit-gradient(linear, left top, left bottom, color-stop(0.05, #4d6b3e), color-stop(1, #42b00a))");
					$("#scheduler_button").css("background", "-webkit-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
					$("#scheduler_button").css("background", "-o-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
					$("#scheduler_button").css("background", "-ms-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
				}

				if (($("#scheduler_name").val() !== '') && ($("#scheduler_input_datetime").val() !== '')){
					$("#scheduler_button").prop('disabled', false);
				}else{
					$("#scheduler_button").prop('disabled', true);
				}
			});
	
			$("#scheduler_name").focus(function() {
				scheduler_name_before = $("#scheduler_name").val();
			});

			$("#scheduler_name").focusout(function() {
				scheduler_name_after = $("#scheduler_name").val();

				if ((scheduler_name_after !== scheduler_name_before) && ($( "#scheduler_list option:selected").text() !== 'Dodaj nowy plan...')){
					$("#scheduler_button").text('Zapisz zmiany');
					
					$("#scheduler_button").css("background", "-moz-linear-gradient(top, #994d00 5%, #ff9933 100%)");
					$("#scheduler_button").css("background", "-webkit-gradient(linear, left top, left bottom, color-stop(0.05, #994d00), color-stop(1, #ff9933))");
					$("#scheduler_button").css("background", "-webkit-linear-gradient(top, #994d00 5%, #ff9933 100%)");
					$("#scheduler_button").css("background", "-o-linear-gradient(top, #994d00 5%, #ff9933 100%)");
					$("#scheduler_button").css("background", "-ms-linear-gradient(top, #994d00 5%, #ff9933 100%)");

					$("#scheduler_button").css("border", "3px solid #ff9933");

					$("#scheduler_button").css("-moz-box-shadow", "0px 10px 14px -7px #ff9933");
					$("#scheduler_button").css("-webkit-box-shadow", "0px 10px 14px -7px #ff9933");
					$("#scheduler_button").css("box-shadow", "0px 10px 14px -7px #ff9933");
				}

				if ((scheduler_name_after !== '') && ($( "#scheduler_list option:selected").text() === 'Dodaj nowy plan...')){
					$("#scheduler_button").text('Dodaj nowy plan');
					
					$("#scheduler_button").css("border", "3px solid #4b8f29");
					$("#scheduler_button").css("text-shadow", "0px 1px 0px #5b8a3c");

					$("#scheduler_button").css("-moz-box-shadow", "0px 10px 14px -7px #3e7327");
					$("#scheduler_button").css("-webkit-box-shadow", "0px 10px 14px -7px #3e7327");
					$("#scheduler_button").css("box-shadow", "0px 10px 14px -7px #3e7327");

					$("#scheduler_button").css("background", "-moz-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
					$("#scheduler_button").css("background", "-webkit-gradient(linear, left top, left bottom, color-stop(0.05, #4d6b3e), color-stop(1, #42b00a))");
					$("#scheduler_button").css("background", "-webkit-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
					$("#scheduler_button").css("background", "-o-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
					$("#scheduler_button").css("background", "-ms-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
				}

				if (($("#scheduler_name").val() !== '') && ($("#scheduler_input_datetime").val() !== '')){
					$("#scheduler_button").prop('disabled', false);
				}else{
					$("#scheduler_button").prop('disabled', true);
				}
			});
	
			//scroll to href on page
			$("#menu_control").click(function() {
				$('html, body').animate({
					scrollTop: ($('#control').offset().top)
				},500);
			});
			
			$("#menu_control_chart").click(function() {
				$('html, body').animate({
					scrollTop: ($('#control_chart_box').offset().top)
				},500);
			});
			
			$("#menu_temperature_chart").click(function() {
				$('html, body').animate({
					scrollTop: ($('#temperature_chart_box').offset().top)
				},500);
			});
			
			$("#menu_scheduler").click(function() {
				$('html, body').animate({
					scrollTop: ($('#scheduler').offset().top)
				},500);
			});
			
			$("[id^=days_0]").change(function(){
				if ($("#scheduler_list option:selected").text() !== 'Dodaj nowy plan...'){
					$("#scheduler_button").text('Zapisz zmiany');
					
					$("#scheduler_button").css("background", "-moz-linear-gradient(top, #994d00 5%, #ff9933 100%)");
					$("#scheduler_button").css("background", "-webkit-gradient(linear, left top, left bottom, color-stop(0.05, #994d00), color-stop(1, #ff9933))");
					$("#scheduler_button").css("background", "-webkit-linear-gradient(top, #994d00 5%, #ff9933 100%)");
					$("#scheduler_button").css("background", "-o-linear-gradient(top, #994d00 5%, #ff9933 100%)");
					$("#scheduler_button").css("background", "-ms-linear-gradient(top, #994d00 5%, #ff9933 100%)");

					$("#scheduler_button").css("border", "3px solid #ff9933");

					$("#scheduler_button").css("-moz-box-shadow", "0px 10px 14px -7px #ff9933");
					$("#scheduler_button").css("-webkit-box-shadow", "0px 10px 14px -7px #ff9933");
					$("#scheduler_button").css("box-shadow", "0px 10px 14px -7px #ff9933");
				}else{
					$("#scheduler_button").text('Dodaj nowy plan');
					
					$("#scheduler_button").css("border", "3px solid #4b8f29");
					$("#scheduler_button").css("text-shadow", "0px 1px 0px #5b8a3c");

					$("#scheduler_button").css("-moz-box-shadow", "0px 10px 14px -7px #3e7327");
					$("#scheduler_button").css("-webkit-box-shadow", "0px 10px 14px -7px #3e7327");
					$("#scheduler_button").css("box-shadow", "0px 10px 14px -7px #3e7327");

					$("#scheduler_button").css("background", "-moz-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
					$("#scheduler_button").css("background", "-webkit-gradient(linear, left top, left bottom, color-stop(0.05, #4d6b3e), color-stop(1, #42b00a))");
					$("#scheduler_button").css("background", "-webkit-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
					$("#scheduler_button").css("background", "-o-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
					$("#scheduler_button").css("background", "-ms-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
				}
			});
	
			$("#device_action, #device_action_type").on('change', function(){
				if ($( "#scheduler_list option:selected").text() !== 'Dodaj nowy plan...'){
					$("#scheduler_button").text('Zapisz zmiany');
					
					$("#scheduler_button").css("background", "-moz-linear-gradient(top, #994d00 5%, #ff9933 100%)");
					$("#scheduler_button").css("background", "-webkit-gradient(linear, left top, left bottom, color-stop(0.05, #994d00), color-stop(1, #ff9933))");
					$("#scheduler_button").css("background", "-webkit-linear-gradient(top, #994d00 5%, #ff9933 100%)");
					$("#scheduler_button").css("background", "-o-linear-gradient(top, #994d00 5%, #ff9933 100%)");
					$("#scheduler_button").css("background", "-ms-linear-gradient(top, #994d00 5%, #ff9933 100%)");
				
					$("#scheduler_button").css("border", "3px solid #ff9933");
					
					$("#scheduler_button").css("-moz-box-shadow", "0px 10px 14px -7px #ff9933");
					$("#scheduler_button").css("-webkit-box-shadow", "0px 10px 14px -7px #ff9933");
					$("#scheduler_button").css("box-shadow", "0px 10px 14px -7px #ff9933");
				}
			});
	
			$("#control_button").click(function(){
				$("#control_button").css("background", "-moz-linear-gradient(top, #A1A1A1 5%, #474747 100%)");
				$("#control_button").css("background", "-webkit-gradient(linear, left top, left bottom, color-stop(0.05, #A1A1A1), color-stop(1, #474747))");
				$("#control_button").css("background", "-webkit-linear-gradient(top, #A1A1A1 5%, #474747 100%)");
				$("#control_button").css("background", "-o-linear-gradient(top, #A1A1A1 5%, #474747 100%)");
				$("#control_button").css("background", "-ms-linear-gradient(top, #A1A1A1 5%, #474747 100%)");

				$("#control_button").css("border", "3px solid #8C8C8C");
				
				$("#control_button").css("-moz-box-shadow", "0px 10px 14px -7px #6E6E6E");
				$("#control_button").css("-webkit-box-shadow", "0px 10px 14px -7px #6E6E6E");
				$("#control_button").css("box-shadow", "0px 10px 14px -7px #6E6E6E");
				
				$("#control_button").text('Czekam...');
				change_state();
			});
			
			$("#scheduler_button").mouseenter(function(){
				if ($("#scheduler_button").text() == 'Usun wpis'){
					$("#scheduler_button").css("background", "-moz-linear-gradient(top, #B00A0A 5%, #6B3E3E 100%)");
					$("#scheduler_button").css("background", "-webkit-gradient(linear, left top, left bottom, color-stop(0.05, #B00A0A), color-stop(1, #6B3E3E))");
					$("#scheduler_button").css("background", "-webkit-linear-gradient(top, #B00A0A 5%, #6B3E3E 100%)");
					$("#scheduler_button").css("background", "-o-linear-gradient(top, #B00A0A 5%, #6B3E3E 100%)");
					$("#scheduler_button").css("background", "-ms-linear-gradient(top, #B00A0A 5%, #6B3E3E 100%)");
				}
				
				if ($("#scheduler_button").text() == 'Dodaj nowy plan'){
					$("#scheduler_button").css("background", "-moz-linear-gradient(top, #42b00a 5%, #4d6b3e 100%)");
					$("#scheduler_button").css("background", "-webkit-gradient(linear, left top, left bottom, color-stop(0.05, #42b00a), color-stop(1, #4d6b3e))");
					$("#scheduler_button").css("background", "-webkit-linear-gradient(top, #42b00a 5%, #4d6b3e 100%)");
					$("#scheduler_button").css("background", "-o-linear-gradient(top, #42b00a 5%, #4d6b3e 100%)");
					$("#scheduler_button").css("background", "-ms-linear-gradient(top, #42b00a 5%, #4d6b3e 100%)");
				}
				
				if ($("#scheduler_button").text() == 'Zapisz zmiany'){
					$("#scheduler_button").css("background", "-moz-linear-gradient(top, #ff9933 5%, #994d00 100%)");
					$("#scheduler_button").css("background", "-webkit-gradient(linear, left top, left bottom, color-stop(0.05, #ff9933), color-stop(1, #994d00))");
					$("#scheduler_button").css("background", "-webkit-linear-gradient(top, #ff9933 5%, #994d00 100%)");
					$("#scheduler_button").css("background", "-o-linear-gradient(top, #ff9933 5%, #994d00 100%)");
					$("#scheduler_button").css("background", "-ms-linear-gradient(top, #ff9933 5%, #994d00 100%)");
				}
			});
			
			$("#scheduler_button").mouseout(function(){
				if ($("#scheduler_button").text() == 'Usun wpis'){
					$("#scheduler_button").css("background", "-moz-linear-gradient(top, #6B3E3E 5%, #B00A0A 100%)");
					$("#scheduler_button").css("background", "-webkit-gradient(linear, left top, left bottom, color-stop(0.05, #6B3E3E), color-stop(1, #B00A0A))");
					$("#scheduler_button").css("background", "-webkit-linear-gradient(top, #6B3E3E 5%, #B00A0A 100%)");
					$("#scheduler_button").css("background", "-o-linear-gradient(top, #6B3E3E 5%, #B00A0A 100%)");
					$("#scheduler_button").css("background", "-ms-linear-gradient(top, #6B3E3E 5%, #B00A0A 100%)");
				}else if ($("#scheduler_button").text() == 'Dodaj nowy plan'){
					$("#scheduler_button").css("background", "-moz-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
					$("#scheduler_button").css("background", "-webkit-gradient(linear, left top, left bottom, color-stop(0.05, #4d6b3e), color-stop(1, #42b00a))");
					$("#scheduler_button").css("background", "-webkit-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
					$("#scheduler_button").css("background", "-o-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
					$("#scheduler_button").css("background", "-ms-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
				}else if ($("#scheduler_button").text() == 'Zapisz zmiany'){
					$("#scheduler_button").css("background", "-moz-linear-gradient(top, #994d00 5%, #ff9933 100%)");
					$("#scheduler_button").css("background", "-webkit-gradient(linear, left top, left bottom, color-stop(0.05, #994d00), color-stop(1, #ff9933))");
					$("#scheduler_button").css("background", "-webkit-linear-gradient(top, #994d00 5%, #ff9933 100%)");
					$("#scheduler_button").css("background", "-o-linear-gradient(top, #994d00 5%, #ff9933 100%)");
					$("#scheduler_button").css("background", "-ms-linear-gradient(top, #994d00 5%, #ff9933 100%)");
				}
			});
			
			$("#control_button").mouseenter(function(){
				if ($("#control_button").text() == 'Włącz'){
					$("#control_button").css("background", "-moz-linear-gradient(top, #42b00a 5%, #4d6b3e 100%)");
					$("#control_button").css("background", "-webkit-gradient(linear, left top, left bottom, color-stop(0.05, #42b00a), color-stop(1, #4d6b3e))");
					$("#control_button").css("background", "-webkit-linear-gradient(top, #42b00a 5%, #4d6b3e 100%)");
					$("#control_button").css("background", "-o-linear-gradient(top, #42b00a 5%, #4d6b3e 100%)");
					$("#control_button").css("background", "-ms-linear-gradient(top, #42b00a 5%, #4d6b3e 100%)");
				}else if ($("#control_button").text() == 'Wyłącz'){
					$("#control_button").css("background", "-moz-linear-gradient(top, #B00A0A 5%, #6B3E3E 100%)");
					$("#control_button").css("background", "-webkit-gradient(linear, left top, left bottom, color-stop(0.05, #B00A0A), color-stop(1, #6B3E3E))");
					$("#control_button").css("background", "-webkit-linear-gradient(top, #B00A0A 5%, #6B3E3E 100%)");
					$("#control_button").css("background", "-o-linear-gradient(top, #B00A0A 5%, #6B3E3E 100%)");
					$("#control_button").css("background", "-ms-linear-gradient(top, #B00A0A 5%, #6B3E3E 100%)");
				}
			});
			
			$("#control_button").mouseout(function(){
				if ($("#control_button").text() == 'Włącz'){
					$("#control_button").css("background", "-moz-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
					$("#control_button").css("background", "-webkit-gradient(linear, left top, left bottom, color-stop(0.05, #4d6b3e), color-stop(1, #42b00a))");
					$("#control_button").css("background", "-webkit-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
					$("#control_button").css("background", "-o-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
					$("#control_button").css("background", "-ms-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
				}else if ($("#control_button").text() == 'Wyłącz'){
					$("#control_button").css("background", "-moz-linear-gradient(top, #6B3E3E 5%, #B00A0A 100%)");
					$("#control_button").css("background", "-webkit-gradient(linear, left top, left bottom, color-stop(0.05, #6B3E3E), color-stop(1, #B00A0A))");
					$("#control_button").css("background", "-webkit-linear-gradient(top, #6B3E3E 5%, #B00A0A 100%)");
					$("#control_button").css("background", "-o-linear-gradient(top, #6B3E3E 5%, #B00A0A 100%)");
					$("#control_button").css("background", "-ms-linear-gradient(top, #6B3E3E 5%, #B00A0A 100%)");
				}
			});
	
			//change control chart period
			$('#control_select_period').on('change', function(){
				if (this.value !== 'choose_period')
					device_chart_foo(this.value);
				else
					var control_period_date = new Date();

				control_period_month_temp = parseInt(control_period_date.getUTCMonth() + 1);
				control_period_date_temp = control_period_date.getDate();
				control_period_hours_temp = control_period_date.getHours();
				control_period_minutes_temp = control_period_date.getMinutes();
				control_period_seconds_temp = control_period_date.getSeconds();

				if (control_period_month_temp < 10)
					control_period_month_temp = String('0' + control_period_month_temp);

				if (control_period_date_temp < 10)
					control_period_date_temp = String('0' + control_period_date_temp);

				if (control_period_hours_temp < 10)
					control_period_hours_temp = String('0' + control_period_hours_temp);

				if (control_period_minutes_temp < 10)
					control_period_minutes_temp = String('0' + control_period_minutes_temp);

				if (control_period_seconds_temp < 10)
					control_period_seconds_temp = String('0' + control_period_seconds_temp);

				var control_period_seconds_temp_date_time = control_period_date.getFullYear() + "-" + control_period_month_temp + "-" + control_period_date_temp + " " + control_period_hours_temp + ":" + control_period_minutes_temp + ":" + control_period_seconds_temp;

				control_choose_period = prompt("Wybierz zakres", control_period_seconds_temp_date_time + " - " + control_period_seconds_temp_date_time);

				if (control_choose_period !== null)
					device_chart_foo(control_choose_period);
			}
		});
		
		//change temperature chart period
		$('#temperature_select_period').on('change', function(){
			if (this.value !== 'choose_period'){
				temperature_chart_foo(this.value);
			}else{
				var temperature_period_date = new Date();

				temperature_period_month_temp = parseInt(temperature_period_date.getUTCMonth() + 1);
				temperature_period_date_temp = temperature_period_date.getDate();
				temperature_period_hours_temp = temperature_period_date.getHours();
				temperature_period_minutes_temp = temperature_period_date.getMinutes();
				temperature_period_seconds_temp = temperature_period_date.getSeconds();

				if (temperature_period_month_temp < 10)
					temperature_period_month_temp = String('0' + temperature_period_month_temp);

				if (temperature_period_date_temp < 10)
					temperature_period_date_temp = String('0' + temperature_period_date_temp);

				if (temperature_period_hours_temp < 10)
					temperature_period_hours_temp = String('0' + temperature_period_hours_temp);

				if (temperature_period_minutes_temp < 10)
					temperature_period_minutes_temp = String('0' + temperature_period_minutes_temp);

				if (temperature_period_seconds_temp < 10)
					temperature_period_seconds_temp = String('0' + temperature_period_seconds_temp);

				var temperature_period_seconds_temp_date_time = temperature_period_date.getFullYear() + "-" + temperature_period_month_temp + "-" + temperature_period_date_temp + " " + temperature_period_hours_temp + ":" + temperature_period_minutes_temp + ":" + temperature_period_seconds_temp;

				temperature_choose_period = prompt("Wybierz zakres", temperature_period_seconds_temp_date_time + " - " + temperature_period_seconds_temp_date_time);

				if (temperature_choose_period !== null)
					temperature_chart_foo(temperature_choose_period);
			}
		});
		
		//show/hide temperature1 serie on chart
		$("#temperature_serie_show_temperature_1").click(function(){
			if ($('#temperature_serie_show_temperature_1').prop('checked')){
				for (i=0; i<2; i++){
					if (temperature_chart_legend.data[i]['title'] == 'Temperatura #1'){
						temperature_chart_legend.data[i]['color'] ='#C4BEBE';
						temperature_chart.hideGraph(temperature_chart.graphs[i]);
						
						break;
					}
				}
			}else{
				for (i=0; i<2; i++){
					if (temperature_chart_legend.data[i]['title'] == 'Temperatura #1'){
						temperature_chart_legend.data[i]['color'] ='#EB2700';
						temperature_chart.showGraph(temperature_chart.graphs[i]);
						
						break;
					}
				}
			}
		});
	
		//show/hide temperature2 serie on chart
		$("#temperature_serie_show_temperature_2").click(function(){
			if ($('#temperature_serie_show_temperature_2').prop('checked')){
				for (i=0; i<2; i++){
					if (temperature_chart_legend.data[i]['title'] == 'Temperatura #2'){
						temperature_chart_legend.data[i]['color'] ='#C4BEBE';
						temperature_chart.hideGraph(temperature_chart.graphs[i]);
						
						break;
					}
				}
			}else{
				for (i=0; i<2; i++){
					if (temperature_chart_legend.data[i]['title'] == 'Temperatura #2'){
						temperature_chart_legend.data[i]['color'] ='#EB9100';
						temperature_chart.showGraph(temperature_chart.graphs[i]);
						
						break;
					}
				}
			}
		});
	
		//add/update/delete scheduler data
		$('#scheduler_button').click(function(){
			if ($("#scheduler_button").text() === 'Usun wpis'){
				$.ajax({
					'timeout': 10000,
					'cache': false,
					'async': false,
					'global': false,
					'url': "insert.php?action=scheduler_delete&id=" + $("#scheduler_list option:selected").val(),
					'dataType': "json",
					'success': function(delete_scheduler_data){
						delete_scheduler_json = delete_scheduler_data;
						get_scheduler();
					}
				});
			}else if ($("#scheduler_button").text() === 'Zapisz zmiany'){
				if ($("#device_action_type option:selected" ).val() === "scheduler_once"){
					$.ajax({
						'timeout': 10000,
						'cache': false,
						'async': false,
						'global': false,
						'url': "insert.php?action=scheduler_update&list_name=" + $("#scheduler_name").val() + "&device_action=" + $("#device_action option:selected").val() + "&datetime=" + $("#scheduler_input_datetime").val() + "&id=" + $("#scheduler_list").val(),
						'dataType': "json",
						'success': function(save_scheduler_data){
							save_scheduler_json = save_scheduler_data;
							get_scheduler();
						}
					});
				}else if ($("#device_action_type option:selected" ).val() === "scheduler_cycle"){
					var scheduler_week_string = "";
					
					for (var i = 0; i<7; i++){
						if($('#days_0' + i).is(':checked')){
							scheduler_week_string = String(scheduler_week_string) + String('1');
						}else{
							scheduler_week_string = String(scheduler_week_string) + String('0');
						}
					}
					
					scheduler_week_string = scheduler_week_string + " " + $("#scheduler_input_datetime").val();
					
					$.ajax({
						'timeout': 10000,
						'cache': false,
						'async': false,
						'global': false,
						'url': "insert.php?action=scheduler_update&list_name=" + $("#scheduler_name").val() + "&device_action=" + $("#device_action option:selected").val() + "&datetime=" + scheduler_week_string + "&id=" + $("#scheduler_list").val(),
						'dataType': "json",
						'success': function(save_scheduler_data){
							save_scheduler_json = save_scheduler_data;
							get_scheduler();
						}
					});
				}
			}else if ($("#scheduler_button").text() === 'Dodaj nowy plan'){
				if ($("#device_action_type option:selected" ).val() === "scheduler_once"){
					$.ajax({
						'timeout': 10000,
						'cache': false,
						'async': false,
						'global': false,
						'url': "insert.php?action=scheduler_add&list_name=" + $("#scheduler_name").val() + "&device_action=" + $("#device_action option:selected").val() + "&datetime=" + $("#scheduler_input_datetime").val() + "&id=" + $("#scheduler_list").val() + "&execute_state=0",
						'dataType': "json",
						'success': function(save_scheduler_data){
							save_scheduler_json = save_scheduler_data;
							get_scheduler();
						}
					});
				}else if ($("#device_action_type option:selected" ).val() === "scheduler_cycle"){
					var scheduler_week_string = "";
					
					for (var i = 0; i<7; i++){
						if($('#days_0' + i).is(':checked')){
							scheduler_week_string = String(scheduler_week_string) + String('1');
						}else{
							scheduler_week_string = String(scheduler_week_string) + String('0');
						}
					}
					
					scheduler_week_string = scheduler_week_string + " " + $("#scheduler_input_datetime").val();
					
					$.ajax({
						'timeout': 10000,
						'cache': false,
						'async': false,
						'global': false,
						'url': "insert.php?action=scheduler_update&list_name=" + $("#scheduler_name").val() + "&device_action=" + $("#device_action option:selected").val() + "&datetime=" + scheduler_week_string + "&id=" + $("#scheduler_list").val() + "&execute_state=0000000",
						'dataType': "json",
						'success': function(save_scheduler_data){
							save_scheduler_json = save_scheduler_data;
							get_scheduler();
						}
					});
				}
			}
		});
		
		//change scheduler button apparence
		$('#scheduler_list').click(function(){
			for (var j = 0; j<7; j++){
				$("#days_0" + j + "_check_img").attr("src","");
				$("#days_0" + j + "_check").hide();
			}
			
			for (var m = 0; m<7; m++){
				if ($('#days_0' + m).val() == m){
					$('#days_0' + m).prop('checked', false);
				}
			}
		
			if ($("#scheduler_list option:selected").text() !== 'Dodaj nowy plan...'){
				$("#scheduler_button").text('Usun wpis');
				
				$("#scheduler_button").css("border", "3px solid #8F2929");
				$("#scheduler_button").css("text-shadow", "0px 1px 0px #8A3C3C");

				$("#scheduler_button").css("-moz-box-shadow", "0px 10px 14px -7px #732727");
				$("#scheduler_button").css("-webkit-box-shadow", "0px 10px 14px -7px #732727");
				$("#scheduler_button").css("box-shadow", "0px 10px 14px -7px #732727");

				$("#scheduler_button").css("background", "-moz-linear-gradient(top, #6B3E3E 5%, #B00A0A 100%)");
				$("#scheduler_button").css("background", "-webkit-gradient(linear, left top, left bottom, color-stop(0.05, #6B3E3E), color-stop(1, #B00A0A))");
				$("#scheduler_button").css("background", "-webkit-linear-gradient(top, #6B3E3E 5%, #B00A0A 100%)");
				$("#scheduler_button").css("background", "-o-linear-gradient(top, #6B3E3E 5%, #B00A0A 100%)");
				$("#scheduler_button").css("background", "-ms-linear-gradient(top, #6B3E3E 5%, #B00A0A 100%)");
			}else if ($("#scheduler_list option:selected").text() === 'Dodaj nowy plan...'){
				$("#scheduler_button").text('Dodaj nowy plan');
				$("#scheduler_name").val('');
				$("#scheduler_input_datetime").val('');
				
				$("#scheduler_button").css("border", "3px solid #4b8f29");
				$("#scheduler_button").css("text-shadow", "0px 1px 0px #5b8a3c");

				$("#scheduler_button").css("-moz-box-shadow", "0px 10px 14px -7px #3e7327");
				$("#scheduler_button").css("-webkit-box-shadow", "0px 10px 14px -7px #3e7327");
				$("#scheduler_button").css("box-shadow", "0px 10px 14px -7px #3e7327");

				$("#scheduler_button").css("background", "-moz-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
				$("#scheduler_button").css("background", "-webkit-gradient(linear, left top, left bottom, color-stop(0.05, #4d6b3e), color-stop(1, #42b00a))");
				$("#scheduler_button").css("background", "-webkit-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
				$("#scheduler_button").css("background", "-o-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
				$("#scheduler_button").css("background", "-ms-linear-gradient(top, #4d6b3e 5%, #42b00a 100%)");
			}
		
			for (var i = 0; i<scheduler_data_json['list_name'].length; i++){
				//new scheduler option has been selected
				if ($( "#scheduler_list option:selected" ).val() == scheduler_data_json['list_name'].length){
					$(".days_slider").hide();
					$(".days").hide();
					
					for (var j = 0; j<7; j++){
						$("#days_0" + j + "_check_img").attr("src","");
						$("#days_0" + j + "_check").hide();
					}
					
				//existing scheduler name has been selected
				}else if (scheduler_data_json['id'][i] == $("#scheduler_list option:selected").val()){
					$("#scheduler_name").val(scheduler_data_json['list_name'][i]);
					
					if (scheduler_data_json['device_action'][i] == '0'){
						$("#device_action option").filter(function(){
							return $(this).val() === "scheduler_action_off"; 
						}).prop('selected', true);
					}else if (scheduler_data_json['device_action'][i] == '1'){
						$("#device_action option").filter(function(){
							return $(this).val() === "scheduler_action_on"; 
						}).prop('selected', true);
					}
					
					var pattern_cycle = /[0-1]{7}\s[0-9]{1,2}:[0-9]{1,2}:[0-9]{1,2}/;
					var pattern_once = /[0-9]{4}-[0-9]{2}-[0-9]{2}\s[0-9]{1,2}:[0-9]{1,2}:[0-9]{1,2}/;
					
					if (pattern_once.test(scheduler_data_json['datetime'][i])){
						$(".days").hide();
						$(".days_slider").hide();
						
						if (scheduler_data_json['execute_state'][i] === '1'){
							$("#days_04_check_img").attr("src","check.png");
							$("#days_04_check").show();
						}else if (scheduler_data_json['execute_state'][i] === '0'){
							$("#days_04_check_img").attr("src","uncheck.png");
							$("#days_04_check").show();
						}
						
						$("#scheduler_input_datetime").val(scheduler_data_json['datetime'][i]);
						$("#device_action_type option").filter(function(){
							return $(this).val() == "scheduler_once"; 
						}).prop('selected', true);
						
						var temperature_period_date = new Date();
				
						temperature_period_month_temp = parseInt(temperature_period_date.getUTCMonth() + 1);
						temperature_period_date_temp = temperature_period_date.getDate();
						temperature_period_hours_temp = temperature_period_date.getHours();
						temperature_period_minutes_temp = temperature_period_date.getMinutes();
						temperature_period_seconds_temp = temperature_period_date.getSeconds();

						if (temperature_period_month_temp < 10)
							temperature_period_month_temp = String('0' + temperature_period_month_temp);

						if (temperature_period_date_temp < 10)
							temperature_period_date_temp = String('0' + temperature_period_date_temp);

						var temperature_period_seconds_temp_date = temperature_period_date.getFullYear() + "-" + temperature_period_month_temp + "-" + temperature_period_date_temp;

						$.datetimepicker.setLocale('pl');
						$('#scheduler_input_datetime').datetimepicker({
							dayOfWeekStart: 1,
							format:'Y-m-d H:i:s',
							lang:'pl',
							startDate:	temperature_period_seconds_temp_date
						});
					}else if (pattern_cycle.test(scheduler_data_json['datetime'][i])){
						$(".days").show();
						$(".days_slider").show();
						
						$("#menu_scheduler").click(function() {
							$('html, body').animate({
								scrollTop: ($('#scheduler').offset().top)
							},500);
						});
						
						$("#scheduler_input_datetime").val(scheduler_data_json['datetime'][i].substring(8));
						$("#device_action_type option").filter(function() {
							return $(this).val() == "scheduler_cycle"; 
						}).prop('selected', true);
						
						$.datetimepicker.setLocale('pl');
						$('#scheduler_input_datetime').datetimepicker({
							datepicker:false,
							format:'H:i:s',
							step:5
						});
					
						for (var j = 0; j<7; j++){
							if ($('#days_0' + j).val() == j){
								if (scheduler_data_json['datetime'][i][j] === '1'){
									$('#days_0' + j).prop('checked', true);
									
									if (scheduler_data_json['execute_state'][i][j] === '1'){
										$("#days_0" + j + "_check_img").attr("src","check.png");
										$("#days_0" + j + "_check").show();
									}else if (scheduler_data_json['execute_state'][i][j] === '0'){
										$("#days_0" + j + "_check_img").attr("src","uncheck.png");
										$("#days_0" + j + "_check").show();
									}
								}else if ((scheduler_data_json['datetime'][i][j] === '0')){
									$('#days_0' + j).prop('checked', false);
								}
							}
						}
					}
				}
			}
		});
		
		$(document).ready(function() {
			get_state();
			get_scheduler();
			device_chart_foo();
			temperature_chart_foo();
		});
	</script>
</head>
<body>
	<div id="menu">
		<ul class="menu_navigation">
			<li>
				<a id="menu_control">Sterowanie</a>
			</li>
			<li>
				<a id="menu_control_chart">Historia sterowania</a>
			</li>
			<li>
				<a id="menu_temperature_chart">Historia temperatury</a>
			</li>
			<li>
				<a id="menu_scheduler">Harmonogram</a>
			</li>
			<li>
				<a href="menu_settings">Ustawienia</a>
			</li>
			<li>
				<a href="backend/logout.php">Wyloguj</a>
			</li>
		</ul>
	</div>
	
	<div id="control">
		<table class="control_summary_table">
			<tr>
				<th class="control_summary_table_head"></th>
				<th class="control_summary_table_head">
					Urządzenie
				</th>
				<th class="control_summary_table_content"></th>
				<th class="control_summary_table_empty"></th>
				<th class="control_summary_table_head">
					Temperatura #1
				</th>
				<th class="control_summary_table_head">
					Temperatura #2
				</th>
			</tr>
			<tr>
				<td class="control_summary_table_title">
					Ostatnia akcja:
				</td>
				<td class="control_summary_table_content">
					<span id="table_last_state"></span>
				</td>
				<td class="control_summary_table_empty"></td>
				<td class="control_summary_table_title">
					Ostatnia wartość:
				</td>
				<td class="control_summary_table_content">
					<span id="table_last_temperature1"></span>
				</td>
				<td class="control_summary_table_content">
					<span id="table_last_temperature2"></span>
				</td>
			</tr>
			<tr>
				<td class="control_summary_table_title">
					Ostatnie sterowanie:
				</td>
				<td class="control_summary_table_content">
					<span id="table_last_control"></span>
				</td>
				<td class="control_summary_table_empty"></td>
				<td class="control_summary_table_title">
					Ostatni pomiar:
				</td>
				<td class="control_summary_table_head" colspan="2">
					<span id="table_last_temperature_timestamp"></span>
				</td>
			</tr>
			<tr>
				<td class="control_summary_table_title">
					Adres IP:
				</td>
				<td class="control_summary_table_content">
					<span id="table_ip"></span>
				</td>
				<td class="control_summary_table_empty"></td>
				<td class="control_summary_table_title">
					Średnia temperatura:
				</td>
				<td class="control_summary_table_content">
					<span id="table_avg_temperature1"></span>
				</td>
				<td class="control_summary_table_content">
					<span id="table_avg_temperature2"></span>
				</td>
			</tr>
			<tr>
				<td class="control_summary_table_title">
					Źródło:
				</td>
				<td class="control_summary_table_content">
					<span id="table_source"></span>
				</td>
				<td class="control_summary_table_empty"></td>
				<td class="control_summary_table_title">
					Maksymalna temperatura:
				</td>
				<td class="control_summary_table_content">
					<span id="table_max_temperature1" title="test"></span>
				</td>
				<td class="control_summary_table_content">
					<span id="table_max_temperature2" title="test2"></span>
				</td>
			</tr>
			<tr>
				<td class="control_summary_table_content"></td>
				<td class="control_summary_table_content"></td>
				<td class="control_summary_table_empty"></td>
				<td class="control_summary_table_title">
					Minimalna temperatura:
				</td>
				<td class="control_summary_table_content">
					<span id="table_min_temperature1"></span>
				</td>
				<td class="control_summary_table_content">
					<span id="table_min_temperature2"></span>
				</td>
			</tr>
			<tr>
				<td class="control_summary_table_content" colspan="6">
					<button id="control_button">
						Włącz
					</button>
				</td>
			</tr>
		</table>
	</div>

	<div id="control_chart_box">
		<div id="control_chart"></div>
		<label class="dropdown">
			<span class="labels">Wybierz zakres: </span>
			<select id="control_select_period">
				<option value="last_day">Ostatnia doba</option>
				<option value="last_week">Ostatni tydzień</option>
				<option value="last_month">Ostatni miesiąć</option>
				<option value="last_year">Ostatni rok</option>
				<option value="whole_period">Cały okres</option>
				<option value="choose_period">Wybierz okres...</option>
			</select>
		</label>
	</div>
	
	<div id="temperature_chart_box">
		<div id="temperature_chart"></div>
		<table id="temperautre_control_table">
			<tr>
				<th class="temperautre_control_table_title">
					<span class="labels">Temperatura #1: </span>
				</th>
				<th class="temperautre_control_table_title">
					<div class="temperature_serie_show" visibility="hidden">
						<input type="checkbox" value="0" id="temperature_serie_show_temperature_1" name="check" />
						<label for="temperature_serie_show_temperature_1"></label>
					</div>
				</th>
				<th class="tg_temperature_checkbox_space"></th>
				<th class="temperautre_control_table_title">
					<span class="labels">Temperatura #2: </span>
				</th>
				<th class="temperautre_control_table_title">
					<div class="temperature_serie_show" visibility="hidden">
						<input type="checkbox" value="1" id="temperature_serie_show_temperature_2" name="check" />
						<label for="temperature_serie_show_temperature_2"></label>
					</div>
				</th>
			</tr>
			<tr>
				<th class="temperautre_control_table_title" colspan="5">
				<label class="dropdown">
					<span class="labels">Wybierz zakres: </span>
					<select id="temperature_select_period">
						<option value="last_day">Ostatnia doba</option>
						<option value="last_week">Ostatni tydzień</option>
						<option value="last_month">Ostatni miesiąć</option>
						<option value="last_year">Ostatni rok</option>
						<option value="whole_period">Cały okres</option>
						<option value="choose_period">Wybierz okres...</option>
					</select>
				</label>
				</th>
			</tr>
		</table>
	</div>

	<div id="scheduler">
		<table id="scheduler_table">
			<tr>
				<td rowspan="8">
					<label id="dropdown">
						<select id="scheduler_list" size="5"></select>
					</label>
				</td>
				<td rowspan="8">
					<label class="input"><input type="text" id="scheduler_name"></label>
					<label class="dropdown">
						<select id="device_action_type">
							<option value="scheduler_cycle">Zadanie cykliczne</option>
							<option value="scheduler_once">Zadanie jednorazowe</option>
						</select>
					</label>
					<label class="dropdown">
						<select id="device_action">
							<option value="1">Włącz</option>
							<option value="0">Wyłącz</option>
						</select>
					</label>
				</td>
				<td rowspan="8">
					<input type="text" id="scheduler_input_datetime">
				</td>
			</tr>
			<tr>
				<td>
					<span class="days">Poniedziałek</span>
				</td>
				<td class="td_days">
					<div class="days_slider" visibility="hidden">
						<input type="checkbox" value="0" id="days_00" name="check"/>
						<label for="days_00"></label>
					</div>
				</td>
				<td id="days_00_check" class="days_check">
					<img id="days_00_check_img" src=""></img>
				</td>
			</tr>
			<tr>
				<td>
					<span class="days">Wtorek</span>
				</td>
				<td class="td_days">
					<div class="days_slider" visibility="hidden">
						<input type="checkbox" value="1" id="days_01" name="check"/>
						<label for="days_01"></label>
					</div>
				</td>
				<td id="days_01_check" class="days_check">
					<img id="days_01_check_img" src=""></img>
				</td>
			</tr>
			<tr>
				<td>
					<span class="days">Środa</span>
				</td>
				<td class="td_days">
					<div class="days_slider" visibility="hidden">
						<input type="checkbox" value="2" id="days_02" name="check"/>
						<label for="days_02"></label>
					</div>
				</td>
				<td id="days_02_check" class="days_check">
					<img id="days_02_check_img" src=""></img>
				</td>
			</tr>
			<tr>
				<td>
					<span class="days">Czwartek</span>
				</td>
				<td class="td_days">
					<div class="days_slider" visibility="hidden">
						<input type="checkbox" value="3" id="days_03" name="check"/>
						<label for="days_03"></label>
					</div>
				</td>
				<td id="days_03_check" class="days_check">
					<img id="days_03_check_img" src=""></img>
				</td>
			</tr>
			<tr>
				<td>
					<span class="days">Piątek</span>
				</td>
				<td class="td_days">
					<div class="days_slider" visibility="hidden">
						<input type="checkbox" value="4" id="days_04" name="check" />
						<label for="days_04"></label>
					</div>
				</td>
				<td id="days_04_check" class="days_check">
					<img id="days_04_check_img" src=""></img>
				</td>
			</tr>
			<tr>
				<td>
					<span class="days">Sobota</span>
				</td>
				<td class="td_days">
					<div class="days_slider" visibility="hidden">
						<input type="checkbox" value="5" id="days_05" name="check" />
						<label for="days_05"></label>
					</div>
				</td>
				<td id="days_05_check" class="days_check">
					<img id="days_05_check_img" src=""></img>
				</td>
			</tr>
			<tr>
				<td>
					<span class="days">Niedziela</span>
				</td>
				<td class="td_days">
					<div class="days_slider" visibility="hidden">
						<input type="checkbox" value="6" id="days_06" name="check" />
						<label for="days_06"></label>
					</div>
				</td>
				<td id="days_06_check" class="days_check">
					<img id="days_06_check_img" src=""></img>
				</td>
			</tr>
			<tr>
				<td colspan="6">
					<button id="scheduler_button"></button>
				</td>
			</tr>
		</table>
	</div>
</body>
</html>