#ifndef Aquarium_h
#define Aquarium_h

#include <NTPClient.h>
#include <WiFiUdp.h>
#include <EEPROM.h>

class Aquarium
{
	private:
		//hardware and wlan connection settings
		const char* WLAN_SSID;
		const char* WLAN_PASS;
		const char* HOST;
		const int ONE_WIRE_BUS;
		const int RELAY_PIN;
		int wifiStatus;
		
		//debug and measurements interval settings
		float temperatureAccuracy;
		int interval;
		int serialDebug;
		int onlineDebug;
		
		//device state and temperature value
		int deviceState;
		float temperature1;
		float temperature2;
	public:
		//constructor
		Aquarium();
		
		//ntp client
		NTPClient timeNtp;
		
		//getters for constanst variables
		const char* getHost();
		const char* getWlanSsid();
		const char* getWlanPassword();
		const int getOneWireBus();
		const int getRelayPin();
		
		//service of eeprom functions
		void initEeprom();
		int saveTemperatureAccuracy(float temperatureAccuracyPram);
		float restoreTemperatureAccuracy();
		void saveInterval(int intervalPram);
		int restoreInterval();
		int saveSerialDebug(int serialDebugPram);
		int restoreSerialDebug();
		int saveOnlineDebug(int onlineDebugPram);
		int restoreOnlineDebug();
		
		//setters and getters for debug and measurements interval settings
		void setTemperatureAccuracy(float temperatureAccuracyPram);
		float getTemperatureAccuracy();
		void setInterval(int intervalPram);
		int getInterval();
		void setSerialDebug(int serialDebugPram);
		int getSerialDebug();
		void setOnlineDebug(int onlineDebugPram);
		int getOnlineDebug();
		void setWifiStatus(int wifiStatusPram);
		int getWifiStatus();
		
		//save and restore device state from eeprom
		int saveDeviceState(int deviceStatePram);
		int restoreDeviceState();
		
		//setters and getters for device state and temperature value
		void setDeviceState(int deviceStatePram);
		int getDeviceState();
		void setTemperature1(float temperature1Pram);
		float getTemperature1();
		void setTemperature2(float temperature2Pram);
		float getTemperature2();
		
		//code and decode crc
		int decodeCrcSimple(long int crcPram);
		int decodeCrc(long int crcPram);
		long int codeCrc();
		
		//print time
		String getFormattedTime();
};
#endif