<p align="center">
  <img src="https://github.com/SuperToma/hitachi-hi-kumo/blob/master/plugin_info/hitachihikumo_icon.png?raw=true" />
</p>

## Jeedom plugin for Hitachi heat pumps
  
This plugin controls your Hitachi indoor units in Jeedom like the Hi-Kumo mobile application.

WARNING: This plugin is not a Hitachi official plugin !  
We decline any responsibility if it stops working (ex: Hitachi's API changes).  
At any time, Hitachi changes can cause this plugin stop working.  

For the moment it only works with air/air heat pumps.  
Please come back to me if you need any other implementation (ex: air/water).

### Configuration

1/ Before using this plugin you must:
>  - Connect your Hi-Box to the router and receivers inside the indoor units
>  - Download the Hi-Kumo mobile application on Google Play or the App Store 
>  - Configure your heat pump elements inside the mobile application

2/ In the plugin configuration page of the plugin set your email and password of your Hi-Kumo account.

![Image credentials configuration](https://github.com/SuperToma/hitachi-hi-kumo/blob/master/docs/images/credentials-configuration.jpg?raw=true)

3/ Inside the plugin click on "Sync Hi-Kumo equipments":

![Image sync Hi-Kumo](https://github.com/SuperToma/hitachi-hi-kumo/blob/master/docs/images/sync-hi-kumo.jpg?raw=true)

Select an element, set it as visible and choose a parent.

It must look like:

![Image dashboard preview](https://github.com/SuperToma/hitachi-hi-kumo/blob/master/docs/images/hitachihikumo_screenshot1.jpg?raw=true)

# FAQ
- When I activate the "Eco mode", it activates this mode on all my indoor units
  > Yes, this behaviour is exactly same as in the Hi-Kumo mobile application

- When I click on "Sync Hi-Kumo equipments" it does not find any element
  > Please check they are recognized on the mobile application

## Technical implementations

A cronjob is calling the Hitachi's API each minute to check if the informations about indoor units are still up-to-date in Jeedom.  
Ex: If the selected temperature has been changed by a remote controller or the mobile application, Jeedom will be noticed after some seconds.

## TODO
 - French translations 
 - Add an option "Temperature offset": on my indoor unit model the temperature sensor is inside the split... the temperature of the room is detected as 21°C but room is really at 20°C
 - Class Hikumo.php must not be "Jeedom dependant", would be great to inject a cache manager and a logger.
