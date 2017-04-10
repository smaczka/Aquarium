#include "Aquarium.h"

WiFiUDP udpNtp;

Aquarium::Aquarium(): ONE_WIRE_BUS(14), RELAY_PIN(12), WLAN_SSID("karolina_i_szymon"), WLAN_PASS("szymonkarolina19891990"), HOST("szymonmaczka.2ap.pl"), timeNtp(udpNtp, "0.pl.pool.ntp.org", 3600)
{
	EEPROM.begin(512);
	initEeprom();
	
	setDeviceState(restoreDeviceState());
	setInterval(restoreInterval());
	setTemperatureAccuracy(restoreTemperatureAccuracy());
	setSerialDebug(restoreSerialDebug());
	setOnlineDebug(restoreOnlineDebug());
}

int Aquarium::saveTemperatureAccuracy(float temperatureAccuracyPram){
	String temperatureAccuracyStr;

	temperatureAccuracyStr = String(temperatureAccuracyPram);

	if (temperatureAccuracyStr.length() < 6){
		for (int i=0; i<temperatureAccuracyStr.length(); i++)
			EEPROM.write(i, temperatureAccuracyStr[i]);

		for (int i=temperatureAccuracyStr.length(); i<6; i++)
			EEPROM.write(i, '#');

		EEPROM.commit();
		delay(50);
		
		return 0;
	}else{
		return -1;
	}
}

void Aquarium::initEeprom(){
	if (EEPROM.read(0) != '1'){
		EEPROM.write(0, '1');
		
		for (int i=1; i<100; i++)
			EEPROM.write(i, '#');
		
		EEPROM.commit();
		delay(50);
	}
}

float Aquarium::restoreTemperatureAccuracy(){
	String temperatureAccuracyStr;
	
	for (int i=0; i<6; i++)
		if (char(EEPROM.read(i))!= '#')
			temperatureAccuracyStr += char(EEPROM.read(i));
		else
			break;
		
	return temperatureAccuracyStr.toFloat();
}

void Aquarium::saveInterval(int intervalPram){
	String intervalStr;

	intervalStr = String(intervalPram);

	for (int i = 0; i < intervalStr.length(); i++)
		EEPROM.write(15 + i, intervalStr[i]);
		
	for (int i = 15 + intervalStr.length(); i < 20; i++)
		EEPROM.write(i, '#');

	EEPROM.commit();
	delay(50);
}

int Aquarium::restoreInterval(){
	String intervalStr;

	for (int i = 15; i < 20; ++i)
		if (char(EEPROM.read(i))!= '#')
			intervalStr += char(EEPROM.read(i));
		else
			break;
		
	return intervalStr.toInt();
}

int Aquarium::saveSerialDebug(int serialDebugPram){
	String serialDebugStr;

	if ((serialDebugPram == 1) || (serialDebugPram == 0)){
		serialDebugStr = String(serialDebugPram);
		EEPROM.write(11, serialDebugStr[0]);
		EEPROM.commit();
		delay(50);
		
		return 0;
	}else{
		return -1;
	}
}

int Aquarium::restoreSerialDebug(){
	String serialDebugStr;

	serialDebugStr = char(EEPROM.read(11));
	
	return serialDebugStr.toInt();
}

int Aquarium::saveOnlineDebug(int onlineDebugPram){
	String onlineDebugStr;

	if ((onlineDebugPram == 1) || (onlineDebugPram == 0)){
		onlineDebugStr = String(onlineDebugPram);
		
		EEPROM.write(10, onlineDebugStr[0]);
		EEPROM.commit();
		delay(50);
		
		return 0;
	}else{
		return -1;
	}
}

int Aquarium::restoreOnlineDebug(){
	String onlineDebugStr;

	onlineDebugStr = char(EEPROM.read(10));
	
	return onlineDebugStr.toInt();
}

int Aquarium::saveDeviceState(int deviceStatePram){
	String deviceStateStr;

	if ((deviceStatePram == 1) || (deviceStatePram == 0)){
		deviceStateStr = String(deviceStatePram);
		EEPROM.write(12, deviceStateStr[0]);
		EEPROM.commit();
		delay(50);
		
		return 0;
	}else{
		return -1;
	}
}

int Aquarium::restoreDeviceState(){
	String serialDebugStr;

	serialDebugStr = char(EEPROM.read(11));
	
	return serialDebugStr.toInt();
}

void Aquarium::setDeviceState(int deviceStatePram){
	deviceState = deviceStatePram;
}

int Aquarium::getDeviceState(){
	return deviceState;
}

const char* Aquarium::getHost(){
	return HOST;
}

const int Aquarium::getOneWireBus(){
	return ONE_WIRE_BUS;
}

const int Aquarium::getRelayPin(){
	return RELAY_PIN;
}

const char* Aquarium::getWlanSsid(){
	return WLAN_SSID;
}

const char* Aquarium::getWlanPassword(){
	return WLAN_PASS;
}

int Aquarium::decodeCrc(long int crcPram){
	long int crc_low, crc_high;
	
	timeNtp.update();
	crc_low = (137 * pow(timeNtp.getHours(), 2)) + (timeNtp.getMinutes() * pow((timeNtp.getSeconds() - 1), 3)) + 1051;
	crc_high = (137 * pow(timeNtp.getHours(), 2)) + (timeNtp.getMinutes() * pow((timeNtp.getSeconds() + 1), 3)) + 1051;
	
	if ((crcPram <= crc_high) && (crcPram >= crc_low))
		return 0;
	else
		return -1;
}

long int Aquarium::codeCrc(){
	long int crc;
	
	timeNtp.update();
	crc = (137 * pow(timeNtp.getHours(), 2)) + (timeNtp.getMinutes() * pow(timeNtp.getSeconds(), 3)) + 1051;
	
	return crc;
}

int Aquarium::decodeCrcSimple(long int crcPram){
	int crc;
	
	timeNtp.update();
	crc = timeNtp.getMinutes() % timeNtp.getHours();
	
	if (crcPram == crc)
		return 0;
	else
		return -1;
}

String Aquarium::getFormattedTime(){
	timeNtp.update();
	
	return timeNtp.getFormattedTime();
}

void Aquarium::setTemperature1(float temperature1Pram){
	temperature1 = temperature1Pram;
}

float Aquarium::getTemperature1(){
	return temperature1;
}

void Aquarium::setTemperature2(float temperature2Pram){
	temperature2 = temperature2Pram;
}

float Aquarium::getTemperature2(){
	return temperature2;
}

void Aquarium::setTemperatureAccuracy(float temperatureAccuracyPram){
	temperatureAccuracy = temperatureAccuracyPram;
}

float Aquarium::getTemperatureAccuracy(){
	return temperatureAccuracy;
}

void Aquarium::setInterval(int intervalPram){
	interval = intervalPram;
}

int Aquarium::getInterval(){
	return interval;
}

void Aquarium::setSerialDebug(int serialDebugPram){
	serialDebug = serialDebugPram;
}

int Aquarium::getSerialDebug(){
	return serialDebug;
}

void Aquarium::setOnlineDebug(int onlineDebugPram){
	onlineDebug = onlineDebugPram;
}

int Aquarium::getOnlineDebug(){
	return onlineDebug;
}

void Aquarium::setWifiStatus(int wifiStatusPram){
	wifiStatus = wifiStatusPram;
}

int Aquarium::getWifiStatus(){
	return wifiStatus;
}