<?php

/**
 * RavPower WD009 Entware tool image builder (for Stock firmwares)
 * 
 * Potentially will allow us to stop hacking our RavPower routers by providing a working Entware package to be mounted and started
 * by the Stock firmware's boot process (RavPower please don't remove this, it's a really nice feature! Expand on it even!!)
 * 
 */

 class RoboFile extends \Robo\Tasks {

    private $entwareInstaller = "http://pkg.entware.net/binaries/mipsel/installer/installer.sh";

    function cleanBuild()
    {
        $this->_exec('umount build/dev');
        $this->_exec('umount build/proc');
        $this->_cleanDir('build');
    }

    function cleanOutput()
    {
        $this->_cleanDir('output');
    }

    function linkBusybox()
    {   
        if(!file_exists('build/bin'))
        {
            $this->_mkdir(['build/bin','build/usr','build/usr/bin', 'build/tmp', 'build/dev', 'build/etc', 'build/opt', 'build/usr/local/bin']);
        }
        
        # We're setting up a very minimal chroot here, using a static busybox. Just enough to bootstrap Entware into the /opt folder inside.
        $this->say("Copying busybox to /bin...");
        $this->_copy('toolchain/busybox', 'build/bin/busybox');
        if(!file_exists("/usr/bin/qemu-mipsel-static"))
        {
            $this->yell("You must have qemu-user-static installed to use this utility.");
            die();
        }
        $this->say("Copying the QEMU Mipsel userland emulator into chroot..");
        $this->_copy('/usr/bin/qemu-mipsel-static','build/usr/bin/qemu-mipsel');
        // We're going to copy the phar for Robo, without it, Robo crashes after chrooting.
        $this->say("Copying Robo's Phar into the chroot...");
        $this->_copy('/usr/local/bin/robo.phar', 'build/usr/local/bin/robo.phar');
        // Copy the system's resolv.conf into the chroot, so that name resolution works.
        $this->_copy('/etc/resolv.conf', 'build/etc/resolv.conf');
        $priorcwd = getcwd(); // Store the previous working directory to restore it.
        chdir("build");
        // Build the /bin directory by populating it with busybox links.
        $this->say("Creating links to busybox...");
        $this->_symlink('/bin/busybox','bin/sh');
        $this->_symlink('/bin/busybox','bin/wget');
        $this->_symlink('/bin/busybox','bin/ls');
        $this->_symlink('/bin/busybox','bin/mkdir');
        $this->_symlink('/bin/busybox', 'bin/chmod');
        $this->_symlink('/bin/busybox', 'bin/grep');
        $this->_symlink('/bin/busybox', 'bin/cat');
        $this->_symlink('/bin/busybox', 'bin/ln');
        $this->_symlink('/bin/busybox', 'bin/rm');
        $this->_symlink('/bin/busybox', 'bin/mount');
        $this->_symlink('/bin/busybox', 'bin/umount');
        chdir($priorcwd); // Restore previous working directory.
        
        $this->_chmod("build/bin/sh", 0777);
        $this->_chmod("build/bin/wget", 0777);
        $this->_chmod("build/bin/busybox", 0777);
        $this->_chmod("build/bin/mkdir", 0777);
        $this->_chmod("build/bin/ls", 0777);
        $this->_chmod("build/bin/chmod", 0777);
        $this->_chmod("build/bin/grep", 0777);
        $this->_chmod("build/bin/cat", 0777);
        $this->_chmod("build/bin/ln", 0777);
        $this->_chmod("build/bin/rm", 0777);
        $this->_chmod("build/bin/mount", 0777);
        $this->_chmod("build/bin/umount", 0777);
        //$this->_exec('chroot build/ /opt/bin/opkg install busybox');
        //$this->_exec("chroot build/ /opt/bin/opkg install entware-opt");
        //$this->_exec("chroot build/ /opt/bin/opkg install dropbear");
    
    }

    function OpkgAddRepository($repository, $type = "src/gz")
    {
        $this->taskWriteToFile("build/opt/etc/opkg.conf")
             ->append(true)
             ->line("$type $repository")
             ->run();
        $this->imageOpkgUpdate();
    }

    function OpkgUpdate()
    {
        if(!file_exists("build/opt/bin/opkg"))
        {
            $this->yell("You need to initialize the project first!");
        }
        $this->imageStartChroot();
        $this->_exec("chroot build/ /opt/bin/opkg update");
        $this->imageStopChroot();
    }

    function OpkgRemovePackage($package)
    {
        if(!file_exists("build/opt/bin/opkg"))
        {
            $this->yell("You need to initialize the project first!");
        }
        $this->imageStartChroot();
        $this->_exec("chroot build/ /opt/bin/opkg remove $package");
        $this->imageStopChroot();
    }

    function OpkgUpgrade($package = null)
    {
        if(!file_exists("build/opt/bin/opkg"))
        {
            $this->yell("You need to initialize the project first!");
        }
        $this->imageStartChroot();
        $this->_exec("chroot build/ /opt/bin/opkg upgrade $package");
        $this->imageStopChroot();
    }

    function OpkgInstallPackage($packageName)
    {
        if(!file_exists("build/opt/bin/opkg"))
        {
            $this->yell("You need to initialize the project first!");
        }
        $this->imageStartChroot();
        $this->_exec("chroot build /opt/bin/opkg install $packageName");
        $this->imageStopChroot();
    }

    function imageInstallEntware()
    {
        $this->imageStartChroot();
        $this->say("Downloading Entware installation script...");
        $installScript = file_get_contents($this->entwareInstaller);
        if(!$installScript)
        {
            $this->yell("We were unable to download the entware installer script.");
            die();
        }
        $this->taskWriteToFile('build/installer.sh')
            ->text($installScript)
            ->run();
        
        $this->_chmod("build/installer.sh", 0777);
        $this->_exec("chroot build/ /bin/sh ./installer.sh");
        $this->opkgInstallPackage("busybox");
        $this->opkgInstallPackage("entware-opt");
        $this->opkgInstallPackage("dropbear");
        $this->say("Copying startup.sh entrypoint to /opt/etc/init.d..");
        $this->_copy("toolchain/scripts/startup.sh", "build/opt/etc/init.d/startup.sh");
        $this->_chmod("build/opt/etc/init.d/startup.sh", 0777);
        $this->imageStopChroot();
    }

    private function imageStartChroot()
    {
        if(!file_exists("build/proc"))
        {
            $this->_mkdir("build/proc");
        }
        if(!file_exists("build/dev"))
        {
            $this->_mkdir("build/dev");
        }
        $this->_exec("chroot build /bin/busybox mount -t proc procfs /proc");
        $this->_exec("mount -o bind /dev build/dev");
    }

    function imageBuildImage()
    {
        $targetSize = "32M";
        if(!file_exists('output'))
        {
            $this->_mkdir("output");
        }
        $this->_exec("fallocate -l $targetSize output/extern_package");
        $this->_exec("mkfs.ext4 ./output/extern_package");
        $tmpMount = $this->_tmpDir();
        $this->_exec("mount ./output/extern_package $tmpMount");
        $this->_exec("tar -C build/opt/ -cvpf - . | tar -C $tmpMount -xvf - 2>&1");
        $this->_exec("umount $tmpMount");
    }

    private function imageStopChroot()
    {
        $this->_exec("umount build/dev");
        $this->_exec("umount build/proc");
    }

    function buildRavpowerWD009()
    {
        $this->cleanBuild();
        $this->cleanOutput();
        $this->linkBusybox();
        $this->imageInstallEntware();
        $this->imageBuildImage();
    }

 }