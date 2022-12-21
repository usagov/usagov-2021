# Setting up XDebug against dev

My current approach to this is to put the XDebug configuration on a special branch that we can deploy to dev (but not to stage or prod). Better would be to be able to just turn on debugging, or to enable it for dev only, all the time.

## Setup via config in this repo

These changes are already present in this branch. 

.circleci/config.yml: To enable deployment of a branch, add the branch name to the list of allowed branches under all of the build-and-deploy jobs for "dev."

.docker/src-cms/etc/php8/conf.d/50_xdebug.ini: This file was in .docker/src-cms for local dev (and was then included in the container via symlink). I also had to make two changes:

  - Remove (comment out) "xdebug.client_host=host.docker.internal"
  - Add xdebug.start_with_request=trigger

I am not certain we need the xdebug.start_with_request line. The intent is to only try to contact the debugger when user's session has the XDEBUG_SESSION cookie set, rather than on every request.

Our build already calls for the appropriate php xdebug package. 


## Setup of your VSCode instance

Debugging Setup here is the same as for local development.

You should also make sure you have checked out the same branch that you've deployed to dev. I shut down my locally-running containers before trying this out, so I haven't verified whether having two hosts potentially talking to the same debugger might be a problem.


## Creating an SSH tunnel on port 9003

This was the tricky part. We've configured VSCode and PHP to make a connection on port 9003 for debugging. We want an ssh tunnel that maps port 9003 on the web server to 9003 on the local development system. Since there should be no route from the CMS web server to your local computer, you need to create a remote tunnel. "cf ssh" supports only the local tunnel syntax. So you need to use ssh, and you need to do some fancy footwork to get the host name of the web server, and to get a password for ssh. 

Reference: "Using other utilities" at https://cloud.gov/knowledge-base/2021-05-17-troubleshooting-ssh-connections/

The only change is that we use port 2222 instead of 22 for ssh.

Retrieve the PROCESS_GUID for the web process for cms. You will copy this to build the host name when you ssh in: 

```
 % cf target -s dev
 % cf curl /v3/apps/$(cf app cms --guid)/processes | jq --raw-output '.resources | .[] | select(.type == "web").guid'
```

Retrieve a one-time ssh passcode: 

``` 
 % cf ssh-code
```

Use ssh, substituting the value you got from the first command for PROCESS_GUID here:

```
 % ssh -p 2222 -R 9003:localhost:9003 cf:PROCESS_GUID/0@ssh.fr.cloud.gov
```

Today, the ssh command above looks like this:

```
 % ssh -p 2222 -R 9003:localhost:9003 cf:9def0121-c7e9-4969-9b42-31f40d5d7cd4/0@ssh.fr.cloud.gov
``` 

You will be prompted for a password; type in what you got from "cf ssh-code".

Now you should be looking at a shell prompt, and if all went well, you can start the debugger in VSCode, set a breakpoint, and get php on the CMS app to connect to it by going to https://cms-dev.usa.gov?XDEBUG_SESSION_START=foo . (Any page will do, and any value works in place of "foo.")

To clear your debugging cookie, you can go to any cms-dev.usa.gov page and append ?XDEBUG_SESSION_STOP.

## Coordinating with other developers

With this setup, only one of us can use the debugger at a time. Aside from the obvious "catch someone in chat," one way to tell if someone is already on is to ssh in (without port forwarding) and use netstat:

```
 % cf ssh cms
 ~ # netstat -a | grep 9003
 tcp        0      0 localhost:9003          0.0.0.0:*               LISTEN      
 netstat: /proc/net/tcp6: No such file or directory
 netstat: /proc/net/udp6: No such file or directory
 netstat: /proc/net/raw6: No such file or directory
```

If you see a line like the one that starts with "tcp," someone has already set up port forwarding. (The "No such file or directory" lines are going to show up regardless.)

I had thought that we could "fail fast" by adding "-o ExitOnForwardFailure=yes" to the ssh command, but this seems not to work.


