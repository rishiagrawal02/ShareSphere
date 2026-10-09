import React, { useState, useEffect } from 'react';
import { MapPin, AlertCircle, Info } from 'lucide-react';
import 'leaflet/dist/leaflet.css';

// Fix for default Leaflet icon paths in Vite bundling
import L from 'leaflet';
import iconRetina from 'leaflet/dist/images/marker-icon-2x.png';
import iconMarker from 'leaflet/dist/images/marker-icon.png';
import iconShadow from 'leaflet/dist/images/marker-shadow.png';

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
  iconRetinaUrl: iconRetina,
  iconUrl: iconMarker,
  shadowUrl: iconShadow,
});

export function LocationPicker({
  latitude,
  longitude,
  onChange,
  defaultCenter = [40.7128, -74.0060], // Default NYC or configurable
  height = '300px',
  label = 'Select Location on Map',
  required = false,
  error = null,
}) {
  const [mapError, setMapError] = useState(false);
  const [latInput, setLatInput] = useState(latitude !== null && latitude !== undefined ? String(latitude) : '');
  const [lngInput, setLngInput] = useState(longitude !== null && longitude !== undefined ? String(longitude) : '');

  useEffect(() => {
    if (latitude !== null && latitude !== undefined) {
      setLatInput(String(latitude));
    }
    if (longitude !== null && longitude !== undefined) {
      setLngInput(String(longitude));
    }
  }, [latitude, longitude]);

  const handleManualLatChange = (e) => {
    const val = e.target.value;
    setLatInput(val);
    const num = parseFloat(val);
    if (!isNaN(num) && num >= -90 && num <= 90) {
      onChange({
        latitude: num,
        longitude: parseFloat(lngInput) || (longitude ?? defaultCenter[1]),
      });
    }
  };

  const handleManualLngChange = (e) => {
    const val = e.target.value;
    setLngInput(val);
    const num = parseFloat(val);
    if (!isNaN(num) && num >= -180 && num <= 180) {
      onChange({
        latitude: parseFloat(latInput) || (latitude ?? defaultCenter[0]),
        longitude: num,
      });
    }
  };

  const currentLat = latitude ?? defaultCenter[0];
  const currentLng = longitude ?? defaultCenter[1];

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300">
          {label} {required && <span className="text-emerald-400">*</span>}
        </label>
        <span className="text-[11px] text-slate-400 flex items-center gap-1">
          <Info size={12} />
          Exact location is snapped for privacy until pickup is confirmed
        </span>
      </div>

      {mapError && (
        <div className="p-3 bg-amber-950/40 border border-amber-800/60 rounded-xl text-xs text-amber-300 flex items-center gap-2">
          <AlertCircle size={14} className="shrink-0" />
          <span>Interactive map tiles unavailable. Please use the manual coordinate inputs below.</span>
        </div>
      )}

      {/* Map Display Card */}
      <div
        style={{ height }}
        className="relative w-full rounded-2xl overflow-hidden border border-slate-800 bg-slate-900 shadow-inner"
      >
        <LeafletMapContainer
          center={[currentLat, currentLng]}
          markerPosition={latitude !== null && longitude !== null ? [latitude, longitude] : null}
          onLocationSelect={(lat, lng) => {
            setLatInput(lat.toFixed(6));
            setLngInput(lng.toFixed(6));
            onChange({ latitude: lat, longitude: lng });
          }}
          onError={() => setMapError(true)}
        />
      </div>

      {/* Manual Coordinate Inputs Fallback */}
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
        <div>
          <label htmlFor="manual-lat-input" className="block text-[11px] text-slate-400 mb-1">
            Latitude (-90 to +90)
          </label>
          <div className="relative">
            <input
              id="manual-lat-input"
              type="number"
              step="any"
              value={latInput}
              onChange={handleManualLatChange}
              placeholder="e.g. 40.712800"
              className="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500"
            />
            <MapPin size={14} className="absolute right-3 top-2.5 text-slate-500" />
          </div>
        </div>

        <div>
          <label htmlFor="manual-lng-input" className="block text-[11px] text-slate-400 mb-1">
            Longitude (-180 to +180)
          </label>
          <div className="relative">
            <input
              id="manual-lng-input"
              type="number"
              step="any"
              value={lngInput}
              onChange={handleManualLngChange}
              placeholder="e.g. -74.006000"
              className="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500"
            />
            <MapPin size={14} className="absolute right-3 top-2.5 text-slate-500" />
          </div>
        </div>
      </div>

      {error && (
        <p role="alert" className="flex items-center gap-1.5 text-xs text-red-400">
          <AlertCircle size={12} className="shrink-0" />
          <span>{error}</span>
        </p>
      )}
    </div>
  );
}

/**
 * Lazy Leaflet Map Loader
 */
function LeafletMapContainer({ center, markerPosition, onLocationSelect, onError }) {
  const [MapComponents, setMapComponents] = useState(null);

  useEffect(() => {
    let isMounted = true;
    import('react-leaflet')
      .then((module) => {
        if (isMounted) {
          setMapComponents({
            MapContainer: module.MapContainer,
            TileLayer: module.TileLayer,
            Marker: module.Marker,
            useMapEvents: module.useMapEvents,
          });
        }
      })
      .catch((err) => {
        onError?.(err);
      });

    return () => {
      isMounted = false;
    };
  }, [onError]);

  if (!MapComponents) {
    return (
      <div className="w-full h-full flex items-center justify-center text-xs text-slate-400">
        Loading interactive map...
      </div>
    );
  }

  const { MapContainer, TileLayer, Marker, useMapEvents } = MapComponents;

  function MapClickHandler() {
    useMapEvents({
      click(e) {
        onLocationSelect(e.latlng.lat, e.latlng.lng);
      },
    });
    return null;
  }

  return (
    <MapContainer
      center={center}
      zoom={13}
      scrollWheelZoom={false}
      style={{ width: '100%', height: '100%' }}
    >
      <TileLayer
        attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
      />
      <MapClickHandler />
      {markerPosition && <Marker position={markerPosition} />}
    </MapContainer>
  );
}
