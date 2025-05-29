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
        <p id="docs-link-paragraph" class="text-gray-400 mb-8 text-lg opacity-0 transform -translate-y-3 transition-all duration-1000 ease-out delay-700">
            API Documentation will be available soon.
            {{-- <a href="/api/documentation" class="text-indigo-400 hover:text-indigo-300 font-semibold underline">View API Documentation</a> --}}
        </p>

        <button id="interactive-button" class="bg-indigo-500 hover:bg-indigo-600 text-white font-semibold py-3 px-8 rounded-lg shadow-lg transition-all duration-300 ease-in-out transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-opacity-75 opacity-0 scale-75 delay-1000">
            Discover More
        </button>

        <p id="interactive-text" class="mt-8 text-purple-300 font-medium opacity-0 transition-all duration-700 ease-in-out h-0 overflow-hidden">
            This API is built with Laravel &hearts;
        </p>
    </div>
</body>
</html>
