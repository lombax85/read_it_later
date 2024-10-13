# Read It Later

Read It Later is a web application that allows users to collect and organize links in a reading list. The app automatically categorizes links by topic and generates text summaries with the ability to request in-depth information on specific sections.

## Project Structure

The project is structured as follows:

- `public/`: Contains the public-facing files
  - `index.html`: The main HTML file
  - `index.php`: The main PHP entry point
  - `js/app.js`: The main JavaScript file
- `src/`: Contains the PHP source files
  - `models/`: Contains the data models
  - `services/`: Contains the service classes
  - `Database.php`: Handles database connections
- `database/`: Contains the SQLite database file
- `vendor/`: Contains Composer dependencies
- `composer.json`: Defines the project dependencies
- `Dockerfile`: Defines the Docker image for the application
- `docker-compose.yml`: Defines the Docker services
- `.env`: Contains environment variables (not tracked in git)

## Setup and Running

To run this project locally, follow these steps:

1. Clone the repository:
   ```
   git clone https://github.com/yourusername/read-it-later.git
   cd read-it-later
   ```

2. Create a `.env` file in the root directory and add your OpenAI API key:
   ```
   OPENAI_API_KEY=your_api_key_here
   ```

3. Make sure you have Docker and Docker Compose installed on your system.

4. Build and start the Docker containers:
   ```
   docker-compose up -d --build
   ```

5. Install PHP dependencies using Composer:
   ```
   docker-compose run --rm app composer install
   ```

6. Set up the database:
   ```
   docker-compose exec app php src/Database.php
   ```

7. The application should now be running. Access it in your web browser at:
   ```
   http://localhost:8080
   ```

## Development

To make changes to the project:

1. Modify the files as needed.
2. If you add new dependencies, update the `composer.json` file and run:
   ```
   docker-compose exec app composer update
   ```
3. Rebuild the Docker image if you make changes to the Dockerfile:
   ```
   docker-compose up -d --build
   ```

## Stopping the Application

To stop the Docker containers, run:
