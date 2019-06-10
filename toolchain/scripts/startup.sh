#!/bin/

# Startup script for running Entware on stock HooToo firmware derived devices.
. /etc/init.d/vstfunc

# Check to see if we've already mounted and someone is trying to hit us twice.
if [ -f /opt/bin/busybox ]; then
    # We are, do nothing.
    echo "[Entware Startup] This script is not intended to be run after startup.";
else
    if [ !-f /opt ]; then
        echo "[Entware Startup] Woops, /opt is missing, creating it!"
        mkdir /opt
    fi
    echo "[Entware Startup] Bind-mounting /extern to /opt for Entware's hardcoded binary prefix."
    mount -o bind /extern /opt
    echo "[Entware Startup] Starting Entware services and userland.."
    /opt/etc/init.d/rc.unslung start
    # If this is the first time it has been run, the shutdown script should be installed. It should only be a link to unslung.
    if [ !-f /etc/rc.d/rc1.d ]; then
        ln -s /opt/etc/init.d/rc.unslung /etc/rc.d/rc1.d/K01unslung # Install a shutdown script.
fi