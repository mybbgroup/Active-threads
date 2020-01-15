# The Active Threads plugin for MyBB

This is a plugin for the [MyBB](https://mybb.com/) forum software version 1.8.*. It adds the following features to MyBB:

1. A "View Active Threads" link in the forum's header for both anonymous viewers and logged in members.
2. The same "View Today's Posts" for anonymous viewers that logged in members normally see in the forum's header.
3. A page which is arrived at via that "View Active Threads" link, which provides:
   1. A listing of threads which over a specified period (defaulting to 14 days) have had one or more posts made to them, in descending order of number of posts made.
   2. The ability to sort the listing by number of posts, and date of earliest/latest post, made during the specified period.
   3. The ability to specify the period to a fidelity of seconds up to any given date-time.
   4. The ability for administrators to set the maximum allowable period in seconds.

What is this plugin useful for?

Two use cases are most likely:

Firstly, to see which threads have been most active in terms of number of posts over the most recent N days, where N defaults to 14.

Secondly, to view, in order from most recent to earliest, the latest post to all threads which have been posted to over a certain (configurable) period, defaulting to 14 days.

## Installing

1. *Download*.

   Download an archive of the plugin's files from its GitHub repository: click "Clone or download" and then "Download zip".

2. *Copy files*.

   Extract the files in that archive to a temporary location, and then copy the files in "root" into the root of your MyBB installation. That is to say that "root/activethreads.php" should be copied to your MyBB root directory, "root/inc/languages/english/activethreads.lang.php" should be copied to your MyBB root's "inc/languages/english/" directory, etc.

3. *Install via the ACP*.

   In a web browser, open the "Plugins" module in the Admin Control Panel (ACP) of your MyBB installation. You should see "Active Threads" under "Inactive Plugins". Click "Install & Activate" next to it. You should then see the plugin listed under "Active Plugins" on the reloaded page.

4. *Configure settings*.

   If (presumably for performance reasons) you wish to limit the maximum configurable period in seconds, then navigate in the ACP to Settings -> Plugin Settings -> Active Threads and enter your preferred value (one week in seconds, for example - a plausible limit for a large board - is 604,800).

That's it. You should now see the "View Active Threads" link in your forum's header. In any case, you should be able to view the plugin's page at http://your-forum.com/root/activethreads.php