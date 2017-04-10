#include <EEPROM.h>
#include <math.h>
#include <ESP8266WiFi.h>
#include <WiFiUdp.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <NTPClient.h>
#include "Wire.h"
#include <Aquarium.h>

//main program object
Aquarium aquarium = Aquarium();

//HTTP Request variables
WiFiClient client;
String serverResponseStr = "", onlineDebugStr = "", httpRequestStr = "";
int stringSearch = -1, requestId, startup = 1;

//UDP variables
WiFiUDP upd;
boolean udpConnected = false;
unsigned int localPort = 8888;
char packetBufferUdp[UDP_TX_PACKET_MAX_SIZE];
char replyBuffer[] = "acknowledged";
String packetBufferStr = "";

//1-wire variables
OneWire oneWire(aquarium.getOneWireBus());
DallasTemperature DS18B20(&oneWire);
float temperature1Prev, temperature2Prev;

unsigned long int millisValue = 0;
long int checkCrc = 0;

void setup(){
	//establish serial connection
	Serial.begin(115200);
	
	//initiate relay pin
	pinMode(aquarium.getRelayPin(), OUTPUT);
	
	//establish wifi connection
	WiFi.begin(aquarium.getWlanSsid(), aquarium.getWlanPassword());
	delay(5000);
	
	//check wifi connectivity
	if (WiFi.status() != WL_CONNECTED){
		aquarium.setWifiStatus(0);
		WiFi.disconnect();
		restartWifi();
	}
	
	if (WiFi.status() != WL_CONNECTED)
		aquarium.setWifiStatus(0);

	if (WiFi.status() == WL_CONNECTED)
		aquarium.setWifiStatus(1);
		
	//initiate 1-wire bus and read temeratures
	DS18B20.begin();
	DS18B20.requestTemperatures(); 

	aquarium.setTemperature1(DS18B20.getTempCByIndex(0));
	aquarium.setTemperature2(DS18B20.getTempCByIndex(1));
	
	temperature1Prev = aquarium.getTemperature1();
	temperature2Prev = aquarium.getTemperature2();
	
	//read last device state from EEPROM and set relay
	if (aquarium.getDeviceState())
		digitalWrite(aquarium.getRelayPin(), HIGH);
	else if (!aquarium.getDeviceState())
		digitalWrite(aquarium.getRelayPin(), LOW);
	
	//establish udp connection and ntp client when wifi connect is available
	if (aquarium.getWifiStatus())
		udpConnected = connectUDP();
	
	//set random seed based on analog input 0
	randomSeed(analogRead(A0));
	
	//print startup debug message if serial debug is enabled
	if (aquarium.getSerialDebug()){
		Serial.println("####			START MESSAGE				 ####");
		Serial.println("#	Device started!");
		Serial.println("#	Temperature of DS18B20 #1 at startup: " + String(aquarium.getTemperature1()));
		Serial.println("#	Temperature of DS18B20 #2 at startup: " + String(aquarium.getTemperature2()));
		Serial.println("#	EEPROM temperature accuracy: " + String(aquarium.getTemperatureAccuracy()));
		Serial.println("#	EEPROM temperature interval: " + String(aquarium.getInterval()));
		
		if (aquarium.getOnlineDebug())
			Serial.println("#	EEPROM online debug is enabled");
		else
			Serial.println("#	EEPROM online debug is disabled");
			
		if (aquarium.getSerialDebug())
			Serial.println("#	EEPROM serial debug is enabled");
		else
			Serial.println("#	EEPROM serial debug is disabled");
				
		if (aquarium.getWifiStatus())
			Serial.println("#	Connected to SSID: " + String(aquarium.getWlanSsid()));
		else
			Serial.println("#	Error! Can not connect to SSID: " + String(aquarium.getWlanSsid()));
		
		Serial.println("####			END MESSAGE				 ####");
		Serial.println();
	}
	
	//send http request startup message if online debug and wifi connection is enabled
	if ((aquarium.getOnlineDebug()) && (aquarium.getWifiStatus())){
		requestId = random(65535);
		onlineDebugStr = "Device%20started!%0AConnected%20to%20SSID:%20" + String(aquarium.getWlanSsid()) + "%0ATemperature%20of%20DS18B20%20#1%20at%20startup:%20" + String(aquarium.getTemperature1()) + "%0ATemperature%20of%20 DS18B20%20#2%20at%20startup:%20" + String(aquarium.getTemperature2());
		httpRequestStr = String("GET /migrate/backend/insert.php?action=debug&request_id=" + String(requestId) + "&debug_info=" + String(onlineDebugStr) + "&crc=" + String(aquarium.codeCrc()) + " HTTP/1.1\r\n") + "Host: " + aquarium.getHost() + "\r\n" + "Connection: close\r\n\r\n";
		
		if (client.connect(aquarium.getHost(), 80)){
			client.print(httpRequestStr);
			delay(10);
			
			//print server response if online debug is enabled
			if (aquarium.getSerialDebug()){
				Serial.println("####			START MESSAGE				 ####");
				Serial.println("#	HTTP request has been sent - debug info: device started");
				Serial.println("#	HTTP request: " + httpRequestStr);
				Serial.println("#	Waiting for server response...");
				Serial.println();
				
				serverResponseStr = "";
					
				while(client.available()){
					serverResponseStr = client.readStringUntil('\r');
					
					if ((serverResponseStr.indexOf("Request " + String(requestId) + " has been added successfully")) >= 0)
						stringSearch = 0;
					
					if (serverResponseStr.indexOf("Could not add request " + String(requestId) + ":") >= 0)
						stringSearch = 1;
					
					if (aquarium.getSerialDebug())
						Serial.print(serverResponseStr);
				}
				
				Serial.println();
				
				if (aquarium.getSerialDebug())
					if (!stringSearch)
						Serial.println("#	Request " + String(requestId) + " has been added successfully");
					else if (stringSearch)
						Serial.println("#	Error! Request " + String(requestId) + " has not been added!");
				
				Serial.println("####			END MESSAGE				 ####");
				Serial.println();
				
				stringSearch = -1;
			}
		}
	}
}

//main loop
void loop(){
	//restart wifi connection when it dropout
	if (!aquarium.getWifiStatus()){
		WiFi.disconnect();
		restartWifi();
	}
	
	//send http request once time at startup - new start of device
	if ((startup) && (aquarium.getWifiStatus())){
		if (client.connect(aquarium.getHost(), 80)){
			requestId = random(65535);
			httpRequestStr = String("GET /insert.php?action=uptime&request_id=" + String(requestId) +"&crc=" + String(aquarium.codeCrc()) + " HTTP/1.1\r\n") + "Host: " + aquarium.getHost() + "\r\n" + "Connection: close\r\n\r\n";
			
			client.print(httpRequestStr);
			delay(10);
			
			if (aquarium.getSerialDebug()){
				Serial.println("####			START MESSAGE				 ####");
				Serial.println("#	HTTP request has been sent - debug info: add new start time");
				Serial.println("#	HTTP request: " + httpRequestStr);
				Serial.println("#	Waiting for server response...");
				Serial.println();
				
				serverResponseStr = "";
				
				while(client.available()){
					serverResponseStr = client.readStringUntil('\r');
					
					if ((serverResponseStr.indexOf("Request " + String(requestId) + " has been added successfully")) >= 0)
						stringSearch = 0;
					
					if (serverResponseStr.indexOf("Could not add request " + String(requestId) + ":") >= 0)
						stringSearch = 1;
					
					if (aquarium.getSerialDebug())
						Serial.print(serverResponseStr);
				}
				
				Serial.println();
				
				if (aquarium.getSerialDebug())
					if (!stringSearch)
						Serial.println("#	Request " + String(requestId) + " has been added successfully");
					else if (stringSearch)
						Serial.println("#	Error! Request " + String(requestId) + " has not been added!");
						
				Serial.println("####			END MESSAGE				 ####");
				Serial.println();
				
				stringSearch = -1;
			}
		}
		
		startup = -1;
	}
	
	//if wifi and upd connection is enabled...
	if ((udpConnected) && (aquarium.getWifiStatus())){
		int packetSize = upd.parsePacket();
		packetBufferStr = "";
		stringSearch = -1;

		//if there is any udp packet...
		if(packetSize){
			IPAddress remoteIp = upd.remoteIP();
			
			//read udp packet and copy it to local buffer string
			upd.read(packetBufferUdp, UDP_TX_PACKET_MAX_SIZE);
			String packetBufferStr(packetBufferUdp);

			//print packet info debug message if serial debug is enabled
			if (aquarium.getSerialDebug()){
				Serial.println("####			START MESSAGE				 ####");
				Serial.println("#	Received: " + packetBufferStr);
				Serial.print("#		Received packet of size ");
				Serial.print(packetSize);
				Serial.print(" from ");

				for (int i=0; i<4; i++){
					Serial.print(remoteIp[i], DEC);
					
					if (i<3)
						Serial.print(".");
				}

				Serial.print(" at port ");
				Serial.println(upd.remotePort());

				Serial.print("#		Packet contents: ");
				Serial.print(packetBufferUdp);
				Serial.println();
				Serial.println("####			END MESSAGE				 ####");
				Serial.println();
			}

			/*
			Check occurance of "change_state_skip_db" key word in udp buffer.
			Set realy state in emergency mode (check simply crc and do not check DB response).
			*/
			if (packetBufferStr.substring(0, packetBufferStr.indexOf('&')) == "change_state_skip_db"){
				checkCrc = packetBufferStr.substring(packetBufferStr.indexOf('&')+2).toInt();
				
				if (aquarium.decodeCrcSimple(checkCrc)){
					changeStateSkipDb();
				}else{
					Serial.println("####			START MESSAGE				 ####");
					Serial.println("#	Error! Wrong simple crc sum!");
					Serial.println("#	Crc value: " + String(checkCrc));
					Serial.println("####			END MESSAGE				 ####");
					Serial.println();
				}
				
			/*
			Check occurance of "change_state" key word in udp buffer.
			Set realy state (check crc and do DB response).
			*/
			}else if (packetBufferStr.substring(0, packetBufferStr.indexOf('&')) == "change_state"){
				checkCrc = packetBufferStr.substring(packetBufferStr.indexOf('&')+2).toInt();
				
				if (aquarium.decodeCrc(checkCrc)){
					changeState();
				}else{
					Serial.println("####			START MESSAGE				 ####");
					Serial.println("#	Error! Wrong complex crc sum!");
					Serial.println("#	Crc value: " + String(checkCrc));
					Serial.println("####			END MESSAGE				 ####");
					Serial.println();
				}
			
			/*
			Check occurance of "save_temperature_accuracy" key word in udp buffer.
			Save temperature accuracy to eeprom.
			*/
			}else if (packetBufferStr.substring(0, packetBufferStr.indexOf('&')) == "save_temperature_accuracy"){
				String temperatureAccuracyUdp = String(packetBufferStr.substring(packetBufferStr.indexOf('&')+1));
				checkCrc = packetBufferStr.substring(packetBufferStr.indexOf('&')+2).toInt();
				
				if (aquarium.decodeCrc(checkCrc)){
					aquarium.saveTemperatureAccuracy(temperatureAccuracyUdp.toFloat());
					aquarium.setTemperatureAccuracy(aquarium.restoreTemperatureAccuracy());
					
					if (aquarium.getSerialDebug()){
						Serial.println("####			START MESSAGE				 ####");
						Serial.println("#	New temperature accuracy value has been added to EEPROM");
						Serial.println("#	Temperature accuracy value: " + String(temperatureAccuracyEeprom));
						Serial.println("####			END MESSAGE				 ####");
						Serial.println();
					}
					
					if (aquarium.getOnlineDebug()){
						if (client.connect(aquarium.getHost(), 80)){
							requestId = random(65535);
							onlineDebugStr = "New%20temperature%20accuracy%20value%20has%20been%20added%20to%20EEPROM.%0ACurrent%20value:%20" + String(aquarium.getTemperatureAccuracy());
							httpRequestStr = String("GET /migrate/backend/insert.php?action=debug&request_id=" + String(requestId) + "&debug_info=" + String(onlineDebugStr) + "&crc=" + String(aquarium.codeCrc()) + " HTTP/1.1\r\n") + "Host: " + aquarium.getHost() + "\r\n" + "Connection: close\r\n\r\n";
							
							client.print(httpRequestStr);
							delay(10);
							
							//print server response if online debug is enabled
							if (aquarium.getSerialDebug()){
								Serial.println("####			START MESSAGE				 ####");
								Serial.println("#	HTTP request has been sent - debug info: new temperature accuracy value has been added to EEPROM");
								Serial.println("#	HTTP request: " + httpRequestStr);
								Serial.println("#	Waiting for server response...");
								Serial.println();
								
								serverResponseStr = "";
									
								while(client.available()){
									serverResponseStr = client.readStringUntil('\r');
									
									if ((serverResponseStr.indexOf("Request " + String(requestId) + " has been added successfully")) >= 0)
										stringSearch = 0;
									
									if (serverResponseStr.indexOf("Could not add request " + String(requestId) + ":") >= 0)
										stringSearch = 1;
									
									if (aquarium.getSerialDebug())
										Serial.print(serverResponseStr);
								}
								
								Serial.println();
								
								if (aquarium.getSerialDebug())
									if (!stringSearch)
										Serial.println("#	Request " + String(requestId) + " has been added successfully");
									else if (stringSearch)
										Serial.println("#	Error! Request " + String(requestId) + " has not been added!");
								
								Serial.println("####			END MESSAGE				 ####");
								Serial.println();
								
								stringSearch = -1;
							}
						}
					}
				}else{
					Serial.println("####			START MESSAGE				 ####");
					Serial.println("#	Error! Wrong complex crc sum!");
					Serial.println("#	Crc value: " + String(checkCrc));
					Serial.println("####			END MESSAGE				 ####");
					Serial.println();
				}
				
			/*
			Check occurance of "save_serial_debug" key word in udp buffer.
			Save serial debug to eeprom.
			*/
			}else if (packetBufferStr.substring(0, packetBufferStr.indexOf('&')) == "save_serial_debug"){
				String serialDebugUdp = String(packetBufferStr.substring(packetBufferStr.indexOf('&')+1));
				checkCrc = packetBufferStr.substring(packetBufferStr.indexOf('&')+2).toInt();
				
				if (aquarium.decodeCrc(checkCrc)){
					aquarium.saveSerialDebug(serialDebugUdp.toInt());
					aquarium.setSerialDebug(aquarium.restoreSerialDebug());
					
					if (aquarium.getSerialDebug()){
						Serial.println("####			START MESSAGE				 ####");
						Serial.println("#	New serial debug value has been added to EEPROM");
						Serial.println("#	Serial debug value: " + String(aquarium.getSerialDebug()));
						Serial.println("####			END MESSAGE				 ####");
						Serial.println();
					}
					
					if (aquarium.getOnlineDebug()){
						if (client.connect(aquarium.getHost(), 80)){
							requestId = random(65535);
							onlineDebugStr = "New%20serial%20debug%20value%20has%20been%20added%20to%20EEPROM.%0ACurrent%20value:%20" + String(aquarium.getSerialDebug());
							httpRequestStr = String("GET /migrate/backend/insert.php?action=debug&request_id=" + String(requestId) + "&debug_info=" + String(onlineDebugStr) + "&crc=" + String(aquarium.codeCrc()) + " HTTP/1.1\r\n") + "Host: " + aquarium.getHost() + "\r\n" + "Connection: close\r\n\r\n";
							
							client.print(httpRequestStr);
							delay(10);
							
							//print server response if online debug is enabled
							if (aquarium.getSerialDebug()){
								Serial.println("####			START MESSAGE				 ####");
								Serial.println("#	HTTP request has been sent - debug info: new serial debug value has been added to EEPROM");
								Serial.println("#	HTTP request: " + httpRequestStr);
								Serial.println("#	Waiting for server response...");
								Serial.println();
								
								serverResponseStr = "";
									
								while(client.available()){
									serverResponseStr = client.readStringUntil('\r');
									
									if ((serverResponseStr.indexOf("Request " + String(requestId) + " has been added successfully")) >= 0)
										stringSearch = 0;
									
									if (serverResponseStr.indexOf("Could not add request " + String(requestId) + ":") >= 0)
										stringSearch = 1;
									
									if (aquarium.getSerialDebug())
										Serial.print(serverResponseStr);
								}
								
								Serial.println();
								
								if (aquarium.getSerialDebug())
									if (!stringSearch)
										Serial.println("#	Request " + String(requestId) + " has been added successfully");
									else if (stringSearch)
										Serial.println("#	Error! Request " + String(requestId) + " has not been added!");
								
								Serial.println("####			END MESSAGE				 ####");
								Serial.println();
								
								stringSearch = -1;
							}
						}
					}
				}else{
					Serial.println("####			START MESSAGE				 ####");
					Serial.println("#	Error! Wrong complex crc sum!");
					Serial.println("#	Crc value: " + String(checkCrc));
					Serial.println("####			END MESSAGE				 ####");
					Serial.println();
				}
				
			/*
			Check occurance of "save_online_debug" key word in udp buffer.
			Save online debug to eeprom.
			*/
			}else if (packetBufferStr.substring(0, packetBufferStr.indexOf('&')) == "save_online_debug"){
				String onlineDebugUdp = String(packetBufferStr.substring(packetBufferStr.indexOf('&')+1));
				checkCrc = packetBufferStr.substring(packetBufferStr.indexOf('&')+2).toInt();
				
				if (aquarium.decodeCrc(checkCrc)){
					aquarium.saveOnlineDebug(onlineDebugUdp.toInt());
					aquarium.setOnlineDebug(aquarium.restoreOnlineDebug());
					
					if (aquarium.getSerialDebug()){
						Serial.println("####			START MESSAGE				 ####");
						Serial.println("#	New online debug value has been added to EEPROM");
						Serial.println("#	Online debug value: " + String(aquarium.getOnlineDebug()));
						Serial.println("####			END MESSAGE				 ####");
						Serial.println();
					}
					
					if (aquarium.getOnlineDebug()){
						if (client.connect(aquarium.getHost(), 80)){
							requestId = random(65535);
							onlineDebugStr = "New%20online%20debug%20value%20has%20been%20added%20to%20EEPROM.%0ACurrent%20value:%20" + String(aquarium.getOnlineDebug());
							httpRequestStr = String("GET /migrate/backend/insert.php?action=debug&request_id=" + String(requestId) + "&debug_info=" + String(onlineDebugStr) + "&crc=" + String(aquarium.codeCrc()) + " HTTP/1.1\r\n") + "Host: " + aquarium.getHost() + "\r\n" + "Connection: close\r\n\r\n";
							
							client.print(httpRequestStr);
							delay(10);
							
							//print server response if online debug is enabled
							if (aquarium.getSerialDebug()){
								Serial.println("####			START MESSAGE				 ####");
								Serial.println("#	HTTP request has been sent - debug info: new online debug value has been added to EEPROM");
								Serial.println("#	HTTP request: " + httpRequestStr);
								Serial.println("#	Waiting for server response...");
								Serial.println();
								
								serverResponseStr = "";
									
								while(client.available()){
									serverResponseStr = client.readStringUntil('\r');
									
									if ((serverResponseStr.indexOf("Request " + String(requestId) + " has been added successfully")) >= 0)
										stringSearch = 0;
									
									if (serverResponseStr.indexOf("Could not add request " + String(requestId) + ":") >= 0)
										stringSearch = 1;
									
									if (aquarium.getSerialDebug())
										Serial.print(serverResponseStr);
								}
								
								Serial.println();
								
								if (aquarium.getSerialDebug())
									if (!stringSearch)
										Serial.println("#	Request " + String(requestId) + " has been added successfully");
									else if (stringSearch)
										Serial.println("#	Error! Request " + String(requestId) + " has not been added!");
								
								Serial.println("####			END MESSAGE				 ####");
								Serial.println();
								
								stringSearch = -1;
							}
						}
					}
				}else{
					Serial.println("####			START MESSAGE				 ####");
					Serial.println("#	Error! Wrong complex crc sum!");
					Serial.println("#	Crc value: " + String(checkCrc));
					Serial.println("####			END MESSAGE				 ####");
					Serial.println();
				}
				
			/*
			Check occurance of "save_interval" key word in udp buffer.
			Save interval to eeprom.
			*/
			}else if (packetBufferStr.substring(0, packetBufferStr.indexOf('&')) == "save_interval"){
				String intervalUdp = String(packetBufferStr.substring(packetBufferStr.indexOf('&')+1));
				checkCrc = packetBufferStr.substring(packetBufferStr.indexOf('&')+2).toInt();
				
				if (aquarium.decodeCrc(checkCrc)){
					aquarium.saveInterval(intervalUdp.toInt());
					aquarium.setInterval(aquarium.restoreInterval());
					
					if (aquarium.getSerialDebug()){
						Serial.println("####			START MESSAGE				 ####");
						Serial.println("#	New interval value has been added to EEPROM");
						Serial.println("#	Interval value: " + String(aquarium.getInterval()));
						Serial.println("####			END MESSAGE				 ####");
						Serial.println();
					}
					
					if (aquarium.getOnlineDebug()){
						if (client.connect(aquarium.getHost(), 80)){
							requestId = random(65535);
							onlineDebugStr = "New%20interval%20value%20has%20been%20added%20to%20EEPROM.%0ACurrent%20value:%20" + String(aquarium.getInterval());
							httpRequestStr = String("GET /migrate/backend/insert.php?action=debug&request_id=" + String(requestId) + "&debug_info=" + String(onlineDebugStr) + "&crc=" + String(aquarium.codeCrc()) +" HTTP/1.1\r\n") + "Host: " + aquarium.getHost() + "\r\n" + "Connection: close\r\n\r\n";
							
							client.print(httpRequestStr);
							delay(10);
							
							//print server response if online debug is enabled
							if (aquarium.getSerialDebug()){
								Serial.println("####			START MESSAGE				 ####");
								Serial.println("#	HTTP request has been sent - debug info: new interval value has been added to EEPROM");
								Serial.println("#	HTTP request: " + httpRequestStr);
								Serial.println("#	Waiting for server response...");
								Serial.println();
								
								serverResponseStr = "";
									
								while(client.available()){
									serverResponseStr = client.readStringUntil('\r');
									
									if ((serverResponseStr.indexOf("Request " + String(requestId) + " has been added successfully")) >= 0)
										stringSearch = 0;
									
									if (serverResponseStr.indexOf("Could not add request " + String(requestId) + ":") >= 0)
										stringSearch = 1;
									
									if (aquarium.getSerialDebug())
										Serial.print(serverResponseStr);
								}
								
								Serial.println();
								
								if (aquarium.getSerialDebug())
									if (!stringSearch)
										Serial.println("#	Request " + String(requestId) + " has been added successfully");
									else if (stringSearch)
										Serial.println("#	Error! Request " + String(requestId) + " has not been added!");
								
								Serial.println("####			END MESSAGE				 ####");
								Serial.println();
								
								stringSearch = -1;
							}
						}
					}
				}else{
					Serial.println("####			START MESSAGE				 ####");
					Serial.println("#	Error! Wrong complex crc sum!");
					Serial.println("#	Crc value: " + String(checkCrc));
					Serial.println("####			END MESSAGE				 ####");
					Serial.println();
				}
				
			/*
			Check occurance of "get_eeprom" key word in udp buffer.
			Get all eeprom values.
			*/
			}else if (packetBufferStr.substring(0, packetBufferStr.indexOf('&')) == "get_eeprom"){
				checkCrc = packetBufferStr.substring(packetBufferStr.indexOf('&')+1).toInt();
				
				if (aquarium.decodeCrc(checkCrc)){
					if (client.connect(aquarium.getHost(), 80)){
						requestId = random(65535);
						httpRequestStr = String("GET /migrate/backend/insert.php?action=get_eeprom&request_id=" + String(requestId) + "&eeprom_temperature_accuracy=" + String(aquarium.getTemperatureAccuracy()) + "&eeprom_interval=" + String(aquarium.getInterval()) + "&eeprom_serial_debug=" + String(aquarium.getSerialDebug()) + "&eeprom_online_debug=" + String(aquarium.getOnlineDebug()) + "&crc=" + String(aquarium.codeCrc()) + " HTTP/1.1\r\n") + "Host: " + aquarium.getHost() + "\r\n" + "Connection: close\r\n\r\n";
						
						client.print(httpRequestStr);
						delay(10);
						
						if (aquarium.getSerialDebug()){
							Serial.println("####			START MESSAGE				 ####");
							Serial.println("#	HTTP request has been sent - debug info: add new start time");
							Serial.println("#	HTTP request: " + httpRequestStr);
							Serial.println("#	Waiting for server response...");
							Serial.println();
							
							serverResponseStr = "";
							
							while(client.available()){
								serverResponseStr = client.readStringUntil('\r');
								
								if ((serverResponseStr.indexOf("Request " + String(requestId) + " has been added successfully")) >= 0)
									stringSearch = 0;
								
								if (serverResponseStr.indexOf("Could not add request " + String(requestId) + ":") >= 0)
									stringSearch = 1;
								
								if (aquarium.getSerialDebug())
									Serial.print(serverResponseStr);
							}
							
							Serial.println();
							
							if (aquarium.getSerialDebug())
								if (!stringSearch)
									Serial.println("#	Request " + String(requestId) + " has been added successfully");
								else if (stringSearch)
									Serial.println("#	Error! Request " + String(requestId) + " has not been added!");
									
							Serial.println("####			END MESSAGE				 ####");
							Serial.println();
							
							stringSearch = -1;
						}
					}
				}else{
					Serial.println("####			START MESSAGE				 ####");
					Serial.println("#	Error! Wrong complex crc sum!");
					Serial.println("#	Crc value: " + String(checkCrc));
					Serial.println("####			END MESSAGE				 ####");
					Serial.println();
				}
			}

			//send replay to UDP received packet
			upd.beginPacket(upd.remoteIP(), upd.remotePort());
			upd.write(replyBuffer);
			upd.endPacket();
			
			Serial.println("####			START MESSAGE				 ####");
			Serial.println("#	UPD response has been sent - debug info");
			Serial.println("#	UDP replay buffer: " + String(replyBuffer));
			Serial.println("####			END MESSAGE				 ####");
			Serial.println();
			
			//send udp buffer debug info
			if (aquarium.getOnlineDebug()){
				if (client.connect(aquarium.getHost(), 80)){
					requestId = random(65535);
					onlineDebugStr = "UPD%20response%20has%20been%20sent%20-%20debug%20info%0AUDP%20remote%20IP:%20" + String(upd.remoteIP()) + "%0AUDP%20remote%20port:%20" + String(upd.remotePort()) + "%0AUDP%20replay%20buffer:%20" + String(replyBuffer);
					httpRequestStr = String("GET /migrate/backend/insert.php?action=debug&request_id=" + String(requestId) + "&debug_info=" + String(onlineDebugStr) + "&crc=" + String(aquarium.codeCrc()) + " HTTP/1.1\r\n") + "Host: " + aquarium.getHost() + "\r\n" + "Connection: close\r\n\r\n";
					
					client.print(httpRequestStr);
					delay(10);
					
					if (aquarium.getSerialDebug()){
						Serial.println("####			START MESSAGE				 ####");
						Serial.println("#	HTTP request has been sent - debug info: UDP response information has been sent");
						Serial.println("#	HTTP request: " + httpRequestStr);
						Serial.println("#	Waiting for server response...");
						Serial.println();
						
						serverResponseStr = "";
							
						while(client.available()){
							serverResponseStr = client.readStringUntil('\r');
							
							if (aquarium.getSerialDebug())
								Serial.print(serverResponseStr);
						}
						
						Serial.println("####			END MESSAGE				 ####");
						Serial.println();
					}
				}
			}
		}
		
		delay(10);
	}

	//check interval of temperature mesaurment
	if (millis() > (millisValue + (aquarium.getInterval() * 1000))){
		if (WiFi.status() != WL_CONNECTED)
			aquarium.setWifiStatus(0);

		if (WiFi.status() == WL_CONNECTED)
			aquarium.setWifiStatus(1);
	
		DS18B20.requestTemperatures();
		aquarium.setTemperature1(DS18B20.getTempCByIndex(0));
		aquarium.setTemperature2(DS18B20.getTempCByIndex(1));
	
		//compare new and old temperature value
		if (((abs(temperature1Prev - aquarium.getTemperature1()) > aquarium.getTemperatureAccuracy()) || (abs(temperature2Prev - aquarium.getTemperature2()) > aquarium.getTemperatureAccuracy())) && (aquarium.getWifiStatus())){
			temperature1Prev = aquarium.getTemperature1();
			temperature2Prev = aquarium.getTemperature2();
			httpRequestStr = String("GET /migrate/backend/insert.php?action=temperature&temp1=" + aquarium.getTemperature1() + "&temp2=" + aquarium.getTemperature2() + "&request_id=" + String(requestId) + "&note=Urzadzenie" + "&crc=" + String(aquarium.codeCrc()) + " HTTP/1.1\r\n") + "Host: " + aquarium.getHost() + "\r\n" + "Connection: close\r\n\r\n";
			
			if (client.connect(aquarium.getHost(), 80)){
				requestId = random(65535);
				client.print(httpRequestStr);
				delay(10);

				if (aquarium.getSerialDebug()){
					Serial.println("####			START MESSAGE				 ####");
					Serial.println("#	HTTP request has been sent - debug info: new value of temperature!");
					Serial.println("#	HTTP request: " + httpRequestStr);
					Serial.println("#	Temperature DS18B20 #1: " + String(aquarium.getTemperature1()));
					Serial.println("#	Temperature DS18B20 #2: " + String(aquarium.getTemperature2()));
					Serial.println("#	Waiting for server response...");
					Serial.println();
				}

				serverResponseStr = "";

				while(client.available()){
					serverResponseStr = client.readStringUntil('\r');

					if ((serverResponseStr.indexOf("Request " + String(requestId) + " has been added successfully")) >= 0)
						stringSearch = 0;

					if (serverResponseStr.indexOf("Could not add request " + String(requestId) + ":") >= 0)
						stringSearch = 1;
					
					
					if (aquarium.getSerialDebug())
						Serial.print(serverResponseStr);
				}

				if (stringSearch == 0){
					if (aquarium.getSerialDebug())
						Serial.println("#	Request " + String(requestId) + " has been added successfully");

					if (aquarium.getOnlineDebug()){
						requestId = random(65535);
						onlineDebugStr = "New%20temperature%20value!%0ATemperature%20#1:*" + String(aquarium.getTemperature1()) + "%0ATemperature%20#2:%20" + String(aquarium.getTemperature2());
						httpRequestStr = String("GET /migrate/backend/insert.php?action=debug&request_id=" + String(requestId) + "&debug_info=" + String(onlineDebugStr) + "&crc=" + String(codeCrc(timeClient)) + " HTTP/1.1\r\n") + "Host: " + aquarium.getHost() + "\r\n" + "Connection: close\r\n\r\n";
						
						if (client.connect(aquarium.getHost(), 80)){
							client.print(httpRequestStr);
							delay(10);
							
							if (aquarium.getSerialDebug()){
								Serial.println("####			START MESSAGE				 ####");
								Serial.println("#	HTTP request has been sent - debug info: temperature has been updated");
								Serial.println("#	Waiting for server response...");
								Serial.println();
								
								serverResponseStr = "";
									
								while(client.available()){
									serverResponseStr = client.readStringUntil('\r');
									
									if (aquarium.getSerialDebug())
										Serial.print(serverResponseStr);
								}
								
								Serial.println("####			END MESSAGE				 ####");
								Serial.println();
							}
						}
					}
				}else if (stringSearch == 1){
					if (aquarium.getSerialDebug())
						Serial.println("#	Error! Request " + String(requestId) + " has not been added!");

					if (aquarium.getOnlineDebug()){
						requestId = random(65535);
						onlineDebugStr = "New%20temperature%20value.%0AError!%20Request%20" + String(requestId) + "%20has%20not%20been%20added!";
						httpRequestStr = String("GET /migrate/backend/insert.php?action=debug&request_id=" + String(requestId) + "&debug_info=" + String(onlineDebugStr) + "&crc=" + String(aquarium.codeCrc()) + " HTTP/1.1\r\n") + "Host: " + aquarium.getHost() + "\r\n" + "Connection: close\r\n\r\n";
						
						if (client.connect(aquarium.getHost(), 80)){
							client.print(httpRequestStr);
							delay(10);
							
							if (aquarium.getSerialDebug()){
								Serial.println("####			START MESSAGE				 ####");
								Serial.println("#	HTTP request has been sent - debug info: temperature has not been updated!");
								Serial.println("#	Waiting for server response...");
								Serial.println();
								
								serverResponseStr = "";
									
								while(client.available()){
									serverResponseStr = client.readStringUntil('\r');
									
									if (aquarium.getSerialDebug())
										Serial.print(serverResponseStr);
								}
								
								Serial.println("####			END MESSAGE				 ####");
								Serial.println();
							}
						}
					}
				}
				
				stringSearch = -1;
			}
		}
		
		//report temperature sensors error
		if (aquarium.getSerialDebug())
			if ((aquarium.getTemperature1() == "0.00") && (aquarium.getTemperature2()))
				Serial.println("Error! Possibly temperature sensor problem!");
		
		millisValue = millis();
	}
}

//establish upd connection
boolean connectUDP(){
	boolean state = false;

	if(upd.begin(localPort) == 1){    
		state = true;
	}

	return state;
}

//restart wifi interface
void restartWifi(void) {
	IPAddress ip = WiFi.localIP();

	Serial.println();
	delay(10);
	Serial.print("ESP8266 IP:");
	delay(10);
	Serial.println(ip);
	delay(10);
	WiFi.disconnect();
	Serial.println();
	delay(10);
	Serial.println();
	delay(10);
	Serial.print("Connecting to ");
	delay(10);
	Serial.println(WLAN_SSID);
	delay(10);
	WiFi.begin(WLAN_SSID, WLAN_PASS);

	for (int i=0; i<20; i++){
		if (WiFi.status() == WL_CONNECTED){
			aquarium.setWifiStatus(1);
			break;
		}
		
		Serial.print(".");
		delay(500);
	}

	Serial.print("ESP8266 IP: ");
	Serial.println(WiFi.localIP());

	delay(300);
}

//change pin output to opposite state
int changeStateSkipDb(){
	//print relay set debug message if serial debug is enabled
	if (aquarium.getSerialDebug()){
		Serial.println("####			START MESSAGE				 ####");
			
		if (!aquarium.getDeviceState())
			Serial.println("#	Relay has been set ON without database update!");
		else if (aquarium.getDeviceState())
			Serial.println("#	Relay has been set OFF without database update!");
			
		Serial.println("####			END MESSAGE				 ####");
		Serial.println();
	}
	
	//send http request - debug message: relay change state
	if (aquarium.getOnlineDebug()){
		requestId = random(65535);
		
		if (!aquarium.getDeviceState())
			onlineDebugStr = "Relay%20has%20been%20set%20ON%20without%20database%20update!";
		else if (aquarium.getDeviceState())
			onlineDebugStr = "Relay%20has%20been%20set%20OFF%20without%20database%20update!";
			
		httpRequestStr = String("GET /migrate/backend/insert.php?action=debug&request_id=" + String(requestId) + "&debug_info=" + String(onlineDebugStr) + "&crc=" + String(aquarium.codeCrc()) + " HTTP/1.1\r\n") + "Host: " + aquarium.getHost() + "\r\n" + "Connection: close\r\n\r\n";
		
		if (client.connect(aquarium.getHost(), 80)){
			client.print(httpRequestStr);
			delay(10);
			
			if (aquarium.getSerialDebug()){
				Serial.println("####			START MESSAGE				 ####");
				
				if (!aquarium.getDeviceState())
					Serial.println("#	HTTP request has been sent - debug info: set relay ON without database update");
				else if (aquarium.getDeviceState())
					Serial.println("#	HTTP request has been sent - debug info: set relay OFF without database update");
					
				Serial.println("#	Waiting for server response...");
				
				serverResponseStr = "";
					
				while(client.available()){
					serverResponseStr = client.readStringUntil('\r');
					
					if (aquarium.getSerialDebug())
						Serial.print(serverResponseStr);
				}
				
				Serial.println("####			END MESSAGE				 ####");
				Serial.println();
			}
		}
	}
	
	//set relay state
	if (!aquarium.getDeviceState()){
		digitalWrite(aquarium.getRelayPin(), HIGH);
		aquarium.setDeviceState(1);
	}else if (aquarium.getDeviceState()){
		digitalWrite(aquarium.getRelayPin(), LOW);
		aquarium.setDeviceState(0);
	}
	
	aquarium.saveDeviceState(aquarium.getDeviceState());
}

int changeState(){
	long int crc;
	int crcCheck = 0;
	
	if (client.connect(aquarium.getHost(), 80)){
		requestId = random(65535);
		
		if (!aquarium.getDeviceState())
			httpRequestStr = String("GET /migrate/backend/insert.php?action=change_state&state=1&request_id=" + String(requestId) + "&note=" + String(packetBufferStr.substring(packetBufferStr.indexOf('&')+1)) + "&crc=" + String(aquarium.codeCrc()) + " HTTP/1.1\r\n") + "Host: " + aquarium.getHost() + "\r\n" + "Connection: close\r\n\r\n";
		else if (aquarium.getDeviceState())
			httpRequestStr = String("GET /migrate/backend/insert.php?action=change_state&state=0&request_id=" + String(requestId) + "&note=" + String(packetBufferStr.substring(packetBufferStr.indexOf('&')+1)) + "&crc=" + String(aquarium.codeCrc()) + " HTTP/1.1\r\n") + "Host: " + aquarium.getHost() + "\r\n" + "Connection: close\r\n\r\n";
			
		client.print(httpRequestStr);
		delay(10);
	
		if (aquarium.getSerialDebug()){
			Serial.println("####			START MESSAGE				 ####");
			Serial.println("#	Request: " + httpRequestStr);
			
			if (!aquarium.getDeviceState())
				Serial.println("#	HTTP request has been sent - debug info: set relay ON");
			else if (aquarium.getDeviceState())
				Serial.println("#	HTTP request has been sent - debug info: set relay OFF");
			
			Serial.println("#	Waiting for server response...");
			Serial.println();
		}
		
		serverResponseStr = "";
		
		while(client.available()){
			serverResponseStr = client.readStringUntil('\r');
			
			crc = serverResponseStr.substring(serverResponseStr.indexOf('&')+1).toInt();
			
			if ((serverResponseStr.indexOf("Request " + String(requestId) + " has been added successfully!")) >= 0)
				stringSearch = 0;
				
			if (serverResponseStr.indexOf("Could not add request " + String(requestId) + ":") >= 0)
				stringSearch = 1;
			
			if (aquarium.getSerialDebug())
				Serial.print(serverResponseStr);
		}

		if (aquarium.getSerialDebug()){
			if (aquarium.decodeCrc(crc)){
				Serial.println("####			START MESSAGE				 ####");
				Serial.println("#	CRC is ok");
				Serial.println("####			END MESSAGE				 ####");
				Serial.println();
				
				crcCheck = 1;
			}else{
				Serial.println("####			START MESSAGE				 ####");
				Serial.println("#	CRC error!");
				Serial.println("####			END MESSAGE				 ####");
				Serial.println();
				
				crcCheck = 0;
			}
		}
		
		if ((!stringSearch) && (crcCheck)){
			if (aquarium.getSerialDebug()){
				Serial.println("#	Request " + String(requestId) + " has been added successfully");
				
				if (!aquarium.getDeviceState())
					Serial.println("#	Relay has been set ON");
				else if (aquarium.getDeviceState())
					Serial.println("#	Relay has been set OFF");
			}
			
			if (aquarium.getOnlineDebug()){
				requestId = random(65535);
				
				if (!aquarium.getDeviceState())
					onlineDebugStr = "Relay%20has%20been%20set%20ON!";
				else if (aquarium.getDeviceState())
					onlineDebugStr = "Relay%20has%20been%20set%20OFF!";
				
				if (client.connect(aquarium.getHost(), 80)){
					httpRequestStr = String("GET /migrate/backend/insert.php?action=debug&request_id=" + String(requestId) + "&debug_info=" + String(onlineDebugStr) + "&crc=" + String(aquarium.codeCrc()) + " HTTP/1.1\r\n") + "Host: " + aquarium.getHost() + "\r\n" + "Connection: close\r\n\r\n";
					
					client.print(httpRequestStr);
					delay(10);
					
					if (aquarium.getSerialDebug()){
						Serial.println("####			START MESSAGE				 ####");
						
						if (!aquarium.getDeviceState())
							Serial.println("#	HTTP request has been sent - debug info: set relay ON");
						else if (aquarium.getDeviceState())
							Serial.println("#	HTTP request has been sent - debug info: set relay OFF");
						
						Serial.println("#	HTTP request has been sent - debug info: set relay ON");
						Serial.println("#	Waiting for server response...");
						Serial.println();
						
						serverResponseStr = "";
							
						while(client.available()){
							serverResponseStr = client.readStringUntil('\r');
							
							if (aquarium.getSerialDebug())
								Serial.print(serverResponseStr);
						}
						
						Serial.println("####			END MESSAGE				 ####");
						Serial.println();
					}
				}
			}
			
			//set relay state
			if (!aquarium.getDeviceState()){
				digitalWrite(aquarium.getRelayPin(), HIGH);
				aquarium.setDeviceState(1);
			}else if (aquarium.getDeviceState()){
				digitalWrite(aquarium.getRelayPin(), LOW);
				aquarium.setDeviceState(0);
			}
			
			aquarium.saveDeviceState(aquarium.getDeviceState());
		}else if (stringSearch){
			if (aquarium.getSerialDebug()){
				Serial.println("#	Error! Request " + String(requestId) + " has not been added!");  
			}
			
			if (aquarium.getOnlineDebug()){
				requestId = random(65535);
				
				if (client.connect(aquarium.getHost(), 80)){
					if (!aquarium.getDeviceState()){
						if (crcCheck)
							onlineDebugStr = "Error!%20Request%20" + String(requestId) + "%20has%20not%20been%20affected!%20Relay%20has%20not%20been%20set%20ON!%20CRC%20checking%20OK";
						else
							onlineDebugStr = "Error!%20Request%20" + String(requestId) + "%20has%20not%20been%20affected!%20Relay%20has%20not%20been%20set%20ON!%20CRC%20checking%20error!";
					}else if (aquarium.getDeviceState()){
						if (crcCheck)
							onlineDebugStr = "Error!%20Request%20" + String(requestId) + "%20has%20not%20been%20affected!%20Relay%20has%20not%20been%20set%20OFF!%20CRC%20checking%20OK";
						else
							onlineDebugStr = "Error!%20Request%20" + String(requestId) + "%20has%20not%20been%20affected!%20Relay%20has%20not%20been%20set%20OFF!%20CRC%20checking%20error!";
					}
							
					client.print(onlineDebugStr);
					delay(10);
					
					if (aquarium.getSerialDebug()){
						Serial.println("####			START MESSAGE				 ####");
						
						if (!aquarium.getDeviceState())
							Serial.println("#	HTTP request has been sent - debug info: Error! Request " + String(requestId) + " has not been affected! Relay has not been set ON!");
						else if (aquarium.getDeviceState())
							Serial.println("#	HTTP request has been sent - debug info: Error! Request " + String(requestId) + " has not been affected! Relay has not been set OFF!");
						
						Serial.println("#	Waiting for server response...");
						Serial.println();
						
						serverResponseStr = "";
							
						while(client.available()){
							serverResponseStr = client.readStringUntil('\r');
							
							if (aquarium.getSerialDebug())
								Serial.print(serverResponseStr);
						}
						
						Serial.println("####			END MESSAGE				 ####");
						Serial.println();
					}
				}
			}
		}
		
		if (aquarium.getSerialDebug()){
			Serial.println("####			END MESSAGE				 ####");
			Serial.println();
		}
		
		return aquarium.getDeviceState();
	}else{
		return -1;
	}
}
