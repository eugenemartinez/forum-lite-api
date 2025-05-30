<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ForumLite API</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-br from-slate-900 to-slate-700 text-gray-100 flex items-center justify-center min-h-screen antialiased overflow-hidden">
    <div class="bg-slate-800 p-8 md:p-12 rounded-xl shadow-2xl text-center max-w-xl mx-auto transform transition-all duration-500 ease-out scale-95 opacity-0" id="landing-container">
        <div class="mb-8">
            <svg id="api-icon" class="w-20 h-20 mx-auto text-indigo-400 transform transition-all duration-1000 ease-out opacity-0 -rotate-12 scale-50" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
        </div>

        <h1 id="main-heading" class="text-4xl md:text-5xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 via-purple-400 to-pink-400 mb-6 opacity-0 transform -translate-y-5 transition-all duration-1000 ease-out delay-300">
            ForumLite API
        </h1>
        <p id="sub-heading" class="text-gray-300 mb-4 text-lg opacity-0 transform -translate-y-3 transition-all duration-1000 ease-out delay-500">
            A sleek, modern backend for your next discussion platform.
        </p>
        <p id="docs-link-paragraph" class="text-gray-400 mb-2 text-lg opacity-0 transform -translate-y-3 transition-all duration-1000 ease-out delay-700">
            <a href="/api/documentation" class="text-indigo-400 hover:text-indigo-300 font-semibold underline">View API Documentation</a>
        </p>
        <p id="github-link-paragraph" class="text-gray-400 mb-8 text-lg opacity-0 transform -translate-y-3 transition-all duration-1000 ease-out delay-900">
            <a href="https://github.com/eugenemartinez/forum-lite-api" target="_blank" rel="noopener noreferrer" class="inline-flex items-center text-indigo-400 hover:text-indigo-300 font-semibold underline">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.026 2.747-1.026.546 1.379.201 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.001 10.001 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd" /></svg>
                View on GitHub
            </a>
        </p>

        <button id="interactive-button" class="bg-indigo-500 hover:bg-indigo-600 text-white font-semibold py-3 px-8 rounded-lg shadow-lg transition-all duration-300 ease-in-out transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-opacity-75 opacity-0 scale-75 delay-1000 cursor-pointer">
            Click Me!
        </button>

        <p id="interactive-text" class="mt-8 text-purple-300 font-medium opacity-0 transition-all duration-700 ease-in-out h-0 overflow-hidden">
            This API is built with Laravel &hearts;
        </p>
    </div>
</body>
</html>
