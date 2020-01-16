# The Active Threads plugin for MyBB

Active Threads is a plugin for the [MyBB](https://mybb.com/) forum software version 1.8.*.

It provides a listing of active[1] threads during a specified period defaulting to the most recent fortnight. In that listing are shown each thread's subject, author, and start date, along with the number of posts to it during the specified period, and the dates and authors of the earliest and latest posts made to it during the period.

[1] An "active" thread is simply a thread which has had at least one post made to it during the period in question.

The listing can be sorted by number of posts, as well as by date of earliest or latest post. It is paginated at 20 threads per page.

The period can be specified to a fidelity of minutes up to any given date-time.

Along with the active threads listing page, this plugin adds the following interface features to MyBB:

1. A "View Active Threads" link in the forum's header for both anonymous viewers and logged in members to access the listing page.
2. The same "View Today's Posts" link for anonymous viewers that logged in members by default see in the forum's header.

In addition, the plugin provides a setting for administrators to set the maximum allowable period in seconds. This is because longer periods can be resource-intensive on the database, and expose your forum to DoS attacks.

What is this plugin useful for?

Two use cases are most likely:

Firstly, to see which threads have been most active in terms of number of posts over a given period, typically the most recent N days, where N defaults to 14.

Secondly, to view, in order from most recent to earliest, the latest post to all threads which have been posted to over a certain (configurable) period, defaulting to the most recent 14 days. This can be achieved by sorting in descending order by the final column (date of most recent post to the thread).

## Installing

1. *Download*.

   Download an archive of the plugin's files from its GitHub repository: click "Clone or download" and then "Download zip".

2. *Copy files*.

   Extract the files in that archive to a temporary location, and then copy the files in "root" into the root of your MyBB installation. That is to say that "root/activethreads.php" should be copied to your MyBB root directory, "root/inc/languages/english/activethreads.lang.php" should be copied to your MyBB root's "inc/languages/english/" directory, etc.

3. *Install via the ACP*.

   In a web browser, open the "Plugins" module in the Admin Control Panel (ACP) of your MyBB installation. You should see "Active Threads" under "Inactive Plugins". Click "Install & Activate" next to it. You should then see the plugin listed under "Active Plugins" on the reloaded page.

4. *Configure settings*.

   If (presumably for performance reasons) you wish to limit the maximum configurable period in seconds, then navigate in the ACP to Settings -> Plugin Settings -> Active Threads and enter your preferred value (one week in seconds, for example - a plausible limit for a large board - is 604,800).

That's it. You should now see the "View Active Threads" link in your forum's header. In any case, you should be able to view the plugin's page at http://your-forum.com/your-forum-root-dir/activethreads.php

## Upgrading

1. *Deactivate*.

   In a web browser, open the "Plugins" module in the Admin Control Panel (ACP) of your MyBB installation and click "Deactivate" beside the "Active Threads" plugin.

2. *Copy files*.

   As in step two for installing above.

3. *Reactivate*.

   As for step one but clicking "Activate" rather than "Deactivate".

This will maintain any settings and template changes that you've made, though if you've made template changes, you may after upgrading need to navigate in the ACP to Templates & Style -> Templates -> Find Updated Templates to properly integrate/update this plugin's templates.