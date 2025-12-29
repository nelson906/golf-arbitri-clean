# Quadranti - Golf Tee Time Simulator

This module is a modernized version of the golf tee time simulator (Quadranti) that calculates and displays starting times for golf tournaments according to Italian Golf Federation technical rules.

## Architecture

The code has been refactored into a modular ES6+ structure with clear separation of concerns:

```
resources/js/quadranti/
├── config.js           # Configuration constants and defaults
├── utils.js            # Utility functions (time calculations, storage, etc.)
├── quadranti-logic.js  # Core business logic for tee time calculations
├── quadranti.js        # Main application entry point and UI controller
└── README.md          # This file
```

## Key Improvements

1. **Modern JavaScript (ES6+)**
   - ES6 modules with proper imports/exports
   - Classes for better organization
   - Arrow functions and modern syntax
   - Async/await for asynchronous operations

2. **Separation of Concerns**
   - Configuration separated into dedicated module
   - Utility functions isolated for reusability
   - Business logic separated from DOM manipulation
   - Clear class structure with single responsibilities

3. **Better Code Organization**
   - Consistent naming conventions
   - Comprehensive JSDoc comments
   - Improved error handling
   - Debounced event handlers to prevent rapid reloads

4. **Enhanced Maintainability**
   - Constants for magic strings
   - Centralized storage management
   - Clear method names and parameters
   - Modular structure for easy testing

## Integration with Laravel

### 1. Blade View Integration

Create a blade view for the quadranti page:

```blade
@extends('layouts.app')

@section('content')
<div id="quadranti-app">
    <!-- Your existing HTML structure here -->
</div>
@endsection

@section('scripts')
<!-- jQuery and jQuery UI -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<!-- Table2Excel for export functionality -->
<script src="/js/jquery.table2excel.js"></script>

<!-- Quadranti modules -->
<script type="module">
    import './quadranti/quadranti.js';
</script>
@endsection
```

### 2. Laravel Mix / Vite Configuration

If using Laravel Mix:

```javascript
// webpack.mix.js
mix.js('resources/js/quadranti/quadranti.js', 'public/js/quadranti')
   .copy('resources/js/quadranti', 'public/js/quadranti');
```

If using Vite:

```javascript
// vite.config.js
export default defineConfig({
    // ... other config
    build: {
        rollupOptions: {
            input: {
                // ... other entries
                quadranti: 'resources/js/quadranti/quadranti.js',
            },
        },
    },
});
```

### 3. API Endpoints

Create Laravel routes and controllers for the AJAX endpoints:

```php
// routes/web.php
Route::post('/api/coordinate-ajax', [QuadrantiController::class, 'getCoordinates']);
Route::post('/api/load-excel', [QuadrantiController::class, 'loadExcel']);
```

```php
// app/Http/Controllers/QuadrantiController.php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\EphemerisService;
use App\Services\ExcelService;

class QuadrantiController extends Controller
{
    public function getCoordinates(Request $request)
    {
        $geoArea = $request->input('geo_area');
        $date = $request->input('start');
        
        // Calculate sunrise/sunset based on geographic area
        $ephemeris = app(EphemerisService::class)->calculate($geoArea, $date);
        
        return response()->json([
            'sunrise' => $ephemeris['sunrise'],
            'sunset' => $ephemeris['sunset']
        ]);
    }
    
    public function loadExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls'
        ]);
        
        $data = app(ExcelService::class)->parseRegistrations($request->file('file'));
        
        return response()->json($data);
    }
}
```

### 4. Update JavaScript API URLs

Update the API endpoints in `quadranti-logic.js`:

```javascript
// Update the fetchEphemerisData method
async fetchEphemerisData(geoArea, date) {
    try {
        const response = await $.ajax({
            url: '/api/coordinate-ajax',  // Updated URL
            type: 'POST',
            dataType: 'json',
            data: { 
                geo_area: geoArea, 
                start: date,
                _token: $('meta[name="csrf-token"]').attr('content')  // CSRF token
            }
        });
        return response;
    } catch (error) {
        console.error('Error fetching ephemeris data:', error);
        return { sunrise: 'N/A', sunset: 'N/A' };
    }
}
```

## Usage

The module provides a complete tee time calculation system with the following features:

- **Double/Single Tee Configuration**: Supports both double tee (1 & 10) and single tee starts
- **Competition Types**: 36-hole and 54-hole competitions
- **Player Management**: Handles both men and women players with configurable flight sizes
- **Time Calculations**: Automatic calculation of tee times with configurable gaps
- **Ephemeris Data**: Sunrise/sunset times based on geographic location
- **Excel Import/Export**: Import player names from Excel and export tee time sheets
- **Persistent Storage**: Saves configuration in localStorage

## Configuration

Default configuration can be modified in `config.js`:

```javascript
export const DEFAULT_CONFIG = {
  players: 144,           // Number of men players
  proette: 48,           // Number of women players
  playersPerFlight: 3,   // Players per flight (2, 3, or 4)
  startTime: '08:00',    // First tee time
  gap: '00:10',         // Time between flights
  // ... other settings
};
```

## Technical Rules Compliance

The simulator follows Italian Golf Federation technical rules:
- Maximum 36 men and 18 women for double tee starts
- Single tee mandatory for less than 78 players
- Single tee recommended up to 93 players
- Proper quadrant distribution for fair competition

## Future Enhancements

1. **Testing**: Add unit tests for business logic
2. **Validation**: Add form validation for player counts and time inputs
3. **Localization**: Extract all Italian text to language files
4. **Responsive Design**: Ensure mobile compatibility
5. **Real-time Updates**: Use Vue.js or React for reactive UI updates
6. **Database Storage**: Save configurations to database instead of localStorage

## Dependencies

- jQuery 3.6+
- jQuery UI 1.13+
- jquery.table2excel.js (for Excel export)
- Bootstrap 5+ (for styling)

## License

This code is part of the golf-arbitri-clean project and follows the same license terms.
