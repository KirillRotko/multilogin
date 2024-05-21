# How to Use

## Steps to Run the Application

1. **Clone the GitHub Repository**

   ```bash
   git clone https://github.com/KirillRotko/multilogin.git
   cd <repository-directory>
   ```

2. **Start Your Multilogin X Agent**

   **Linux:**
   ```bash
   /opt/mlx/agent.bin
   ```
   Or simply:
   ```bash
   mlx
   ```

3. **Install Dependencies**

   Run the following command in the project directory to install all the necessary dependencies:
   ```bash
   composer install
   ```

4. **Setup Environment File**

   Rename the `.env_example` file to `.env`, or create a new `.env` file with the following content:
   You need convert your password in md5 format [here](https://www.md5hashgenerator.com)

   ```env
   MLX_EMAIL=youremail@yourdomain.com
   MLX_PASSWORD=VerySecretPassword@123
   WORKSPACE_NAME=yourworkspacename
   ```

5. **Configure the Application**

   Open the `config.php` file and adjust the configuration according to your requirements. For example, you can set up different proxies:

   ```php
   <?php
   // Set 1000 different proxies
   $proxies = [];

   for ($i = 1; $i <= 1000; $i++) {
       $proxies[] = [
           'username' => "rtixxerh-$i",
           'password' => '72szql5eb4bh',
           'host' => 'p.webshare.io',
           'port' => '80',
           'type' => 'http'
       ];
   }

   $config = [
       'extensions' => [
           /* Example - 
           "/extensions/adblock",
           "/extensions/colorzilla",
           "/extensions/pixel-perfect" 
           */
       ],
       'proxies' => $proxies,
       'websites' => [
           "https://wikipedia.org/",
           "https://multilogin.com/",
           "https://dell.com/",
           "https://reddit.com/",
           "https://youtube.com/",
           "https://www.twitch.tv",
           "https://discord.com",
           "https://www.amazon.com",
           "https://hyperx.com",
           "https://secretlab.co",
           "https://store.steampowered.com"
       ],
       'visitDuration' => 30,
       'visitTimeout' => 3600,
       'moveMouseRandomly' => true,
       'maxProcesses' => 2,
   ];
   ```

6. **Run the Application**

   You can now run the application with the following command:

   To create profiles by proxies and run it, use:

   ```bash
   php main.php
   ```

   To run profiles without creating, use:

   ```bash
   php main.php --run
   ```

   To update profiles, use:

   ```bash
   php main.php --update
   ```

   To delete profiles, use:
   ```bash
   php main.php --delete
   ```

## Configuration Details

- **extensions**: Array of paths to the browser extensions you want to load.
- **proxies**: Array of proxy configurations. Each proxy should have a `username`, `password`, `host`, `port`, and `type`.
- **websites**: List of websites to visit.
- **visitDuration**: Duration (in seconds) to stay on each website.
- **visitTimeout**: Timeout (in minutes) before the next batch of visits starts.
- **moveMouseRandomly**: Boolean to enable or disable random mouse movements.
- **maxProcesses**: Maximum number of concurrent processes to run. (Not recomemnded to set higher than 5, each one is runningprofile browser. So the higher the value, the more PC resources it will consume)

## How to install extensions

1. Visit the [Chrome Web Store](https://chromewebstore.google.com/?hl=en-GB).
2. Choose an extension and copy its ID from the address bar: https://chromewebstore.google.com/detail/extension-name/extension-id
3. Paste the ID into [CRXViewer](https://crxviewer.com) and select "Download as ZIP".
4. Extract your ZIP file into a new folder into extensions folder of the project. 
5. Open the `config.php` file and write path to your extension folder in the extensions array

```php
'extensions' => [
           /* Example - 
           "/extensions/adblock",
           "/extensions/colorzilla",
           "/extensions/pixel-perfect" 
           */
       ],
```

By following these steps, you should be able to successfully configure and run the application. Adjust the settings as necessary to fit your specific use case.
