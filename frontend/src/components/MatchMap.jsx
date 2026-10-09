import React, { useState, useEffect } from 'react';
import { MapPin, AlertCircle, Info, Award, HeartHandshake } from 'lucide-react';
import 'leaflet/dist/leaflet.css';

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

export function MatchMap({
  donorLocation, // [lat, lng]
  matches = [],
  height = '420px',
  onSelectMatch,
}) {
  const [mapError, setMapError] = useState(false);
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
            Popup: module.Popup,
            Circle: module.Circle,
          });
        }
      })
      .catch((err) => {
        setMapError(true);
      });

    return () => {
      isMounted = false;
    };
  }, []);

  const defaultCenter = donorLocation && !isNaN(donorLocation[0]) && !isNaN(donorLocation[1])
    ? donorLocation
    : [28.6139, 77.2090]; // Default fallback

  if (mapError) {
    return (
      <div className="p-6 bg-slate-900 border border-slate-800 rounded-2xl text-center space-y-2">
        <AlertCircle size={24} className="mx-auto text-amber-400" />
        <h4 className="text-sm font-semibold text-white">Interactive Map Unavailable</h4>
        <p className="text-xs text-slate-400 max-w-md mx-auto">
          We could not load the map tiles. You can still review all matched NGOs and their distance in the List View.
        </p>
      </div>
    );
  }

  if (!MapComponents) {
    return (
      <div
        style={{ height }}
        className="w-full rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-center text-xs text-slate-400"
      >
        <div className="flex items-center gap-2">
          <div className="w-4 h-4 border-2 border-emerald-500 border-t-transparent rounded-full animate-spin" />
          <span>Loading community matching map...</span>
        </div>
      </div>
    );
  }

  const { MapContainer, TileLayer, Marker, Popup, Circle } = MapComponents;

  // Filter matches that have valid coordinates
  const validMatches = matches.filter(
    (m) => m.latitude != null && m.longitude != null && !isNaN(parseFloat(m.latitude)) && !isNaN(parseFloat(m.longitude))
  );

  return (
    <div className="space-y-2">
      <div
        style={{ height }}
        className="relative w-full rounded-2xl overflow-hidden border border-slate-800 bg-slate-900 shadow-inner"
      >
        <MapContainer
          center={defaultCenter}
          zoom={12}
          scrollWheelZoom={false}
          style={{ width: '100%', height: '100%' }}
        >
          <TileLayer
            attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
          />

          {/* Donor Pin */}
          {donorLocation && !isNaN(donorLocation[0]) && !isNaN(donorLocation[1]) && (
            <>
              <Marker position={donorLocation}>
                <Popup>
                  <div className="p-1 text-slate-900">
                    <div className="flex items-center gap-1.5 font-bold text-xs text-emerald-700">
                      <HeartHandshake size={14} />
                      <span>Your Donation Location</span>
                    </div>
                    <p className="text-[11px] text-slate-600 mt-1">
                      Matched NGOs are scored and shown based on distance to this point.
                    </p>
                  </div>
                </Popup>
              </Marker>
              <Circle
                center={donorLocation}
                radius={2000}
                pathOptions={{ color: '#10b981', fillColor: '#10b981', fillOpacity: 0.08 }}
              />
            </>
          )}

          {/* Matched NGO Pins */}
          {validMatches.map((m, idx) => {
            const lat = parseFloat(m.latitude);
            const lng = parseFloat(m.longitude);
            const score = Math.round(m.total_score ?? m.score ?? 0);
            const dist = m.distance_km != null ? Number(m.distance_km).toFixed(1) : null;

            return (
              <Marker key={m.id || idx} position={[lat, lng]}>
                <Popup>
                  <div className="p-1 text-slate-900 max-w-xs">
                    <div className="flex items-center justify-between gap-2 mb-1">
                      <span className="text-[10px] font-bold px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800">
                        {score}% Match
                      </span>
                      {dist && (
                        <span className="text-[10px] text-slate-500 font-medium">
                          ~{dist} km away
                        </span>
                      )}
                    </div>
                    <h5 className="font-bold text-xs text-slate-900 leading-snug">
                      {m.requirement_title || m.title || 'NGO Need'}
                    </h5>
                    <p className="text-[11px] text-emerald-700 font-medium">
                      {m.organization_name || m.ngo_name || 'Verified NGO'}
                    </p>
                    <p className="text-[10px] text-slate-600 mt-1">
                      Needs: <strong>{m.quantity_needed ?? m.quantity ?? '—'} units</strong>
                    </p>
                    {onSelectMatch && (
                      <button
                        type="button"
                        onClick={() => onSelectMatch(m)}
                        className="mt-2 w-full text-center px-2 py-1 text-[11px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded transition-colors"
                      >
                        View Details
                      </button>
                    )}
                  </div>
                </Popup>
              </Marker>
            );
          })}
        </MapContainer>
      </div>

      <div className="flex items-center justify-between text-[11px] text-slate-400 px-1">
        <span className="flex items-center gap-1">
          <Info size={12} />
          NGO coordinates are privacy-snapped for security (~550m grid).
        </span>
        <span>Showing {validMatches.length} mapped {validMatches.length === 1 ? 'match' : 'matches'}</span>
      </div>
    </div>
  );
}
