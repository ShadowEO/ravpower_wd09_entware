# Entware for Stock Ravpower WD009 routers (May work for other HooToo routers)
### Don't remove this HooToo (please!?!), this is so useful!


## Dependencies:
- PHP
- Robo (robo.li)

# How this works
It turns out that the stock rc.local script looks on the mounted volumes for a disk image with the filename of `extern_package`. It
mounts this file under /extern, and then executes /extern/etc/init.d/startup.sh. I figured that this would be useful for setting up a
persistent Entware-ng (because normal Entware doesn't support this device) by creating a startup.sh that bind-mounts /extern to /opt
and then starts Entware up.

# Why did you do this?
I did this so we could stop relying on a script that we can't see to enable Telnet and other functions. That, and telnet is rather insecure.
The fact that this works on the stock firmware with **no** changes to the rootfs, means that we can stop hacking the router with an
`EnterRouterMode.sh` script! It's much easier, and provides a much larger range of functions for a toolset.

# How to use
If you have a Ravpower WD009 device, or another HooToo based device, simply run `sudo robo build:ravpower-wd009` and then copy the
extern_package image to the .vst folder on your SD card. Reboot the router and then attempt to connect via SSH, you'll find a dropbear
shell waiting!

# Can I help with this?
Certainly! I'm working on making the build environment more flexible, and able to be configured via a YAML file.
