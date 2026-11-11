import { divIcon, latLngBounds } from "leaflet";
import { useEffect, useState } from "react";
import {
  MapContainer,
  Marker,
  TileLayer,
  useMap,
  useMapEvents,
} from "react-leaflet";
import "leaflet/dist/leaflet.css";
import {
  isWithinSyria,
  parseSyriaCoordinates,
  SYRIA_MAP_LIMITS,
  type CoordinateValues,
  type Coordinates,
} from "./syriaLocation";

const initialMapCenter: [number, number] = [33.5138, 36.2765];
const syriaMapBounds = latLngBounds(
  [SYRIA_MAP_LIMITS.south, SYRIA_MAP_LIMITS.west],
  [SYRIA_MAP_LIMITS.north, SYRIA_MAP_LIMITS.east],
);
const syriaCountryBounds = latLngBounds(
  [32.311354, 35.716569],
  [37.318459, 42.374569],
);
const counselorMarkerIcon = divIcon({
  className: "counselor-location-marker",
  iconSize: [28, 38],
  iconAnchor: [14, 37],
});

function MapClickHandler({
  onSelect,
  onInvalidSelection,
}: {
  onSelect: (coordinates: Coordinates) => void;
  onInvalidSelection: () => void;
}) {
  useMapEvents({
    click({ latlng }) {
      const coordinates = { lat: latlng.lat, lng: latlng.lng };
      if (!isWithinSyria(coordinates)) {
        onInvalidSelection();
        return;
      }
      onSelect(coordinates);
    },
  });
  return null;
}

function MapCenter({ coordinates }: { coordinates: Coordinates | null }) {
  const map = useMap();
  useEffect(() => {
    if (coordinates) {
      map.setView(
        [coordinates.lat, coordinates.lng],
        Math.max(map.getZoom(), 13),
      );
    }
  }, [coordinates?.lat, coordinates?.lng, map]);
  return null;
}

function MapCountryView({ showCountry }: { showCountry: boolean }) {
  const map = useMap();
  useEffect(() => {
    if (showCountry) {
      map.fitBounds(syriaCountryBounds, { padding: [12, 12] });
    }
  }, [map, showCountry]);
  return null;
}

export default function CounselorLocationMap({
  values,
  onSelect,
}: {
  values: CoordinateValues;
  onSelect: (coordinates: Coordinates) => void;
}) {
  const mapTilerKey = import.meta.env.VITE_MAPTILER_KEY?.trim();
  const [selectionError, setSelectionError] = useState(false);
  const coordinates = parseSyriaCoordinates(values);
  const position: [number, number] | null = coordinates
    ? [coordinates.lat, coordinates.lng]
    : null;
  useEffect(() => {
    if (coordinates) setSelectionError(false);
  }, [coordinates?.lat, coordinates?.lng]);

  if (!mapTilerKey) {
    return (
      <div className="counselor-map counselor-map-message" role="status">
        الخريطة غير متاحة حالياً. أضف VITE_MAPTILER_KEY إلى ملف .env.local
        لعرضها؛ يمكنك إدخال الإحداثيات يدوياً في الحقول أعلاه.
      </div>
    );
  }

  return (
    <div className="counselor-map">
      <MapContainer
        center={position || initialMapCenter}
        zoom={position ? 13 : 7}
        maxBounds={syriaMapBounds}
        maxBoundsViscosity={1}
        scrollWheelZoom={false}
        className="counselor-map-canvas"
      >
        <TileLayer
          attribution='&copy; <a href="https://www.maptiler.com/copyright/">MapTiler</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>'
          url={`https://api.maptiler.com/maps/streets-v2/{z}/{x}/{y}.png?key=${encodeURIComponent(mapTilerKey)}&language=ar`}
        />
        <MapClickHandler
          onSelect={(selected) => {
            setSelectionError(false);
            onSelect(selected);
          }}
          onInvalidSelection={() => setSelectionError(true)}
        />
        <MapCountryView showCountry={!coordinates} />
        <MapCenter coordinates={coordinates} />
        {position && (
          <Marker
            position={position}
            icon={counselorMarkerIcon}
            draggable
            eventHandlers={{
              dragend: (event) => {
                const point = event.target.getLatLng();
                const selected = { lat: point.lat, lng: point.lng };
                if (!isWithinSyria(selected)) {
                  event.target.setLatLng(position);
                  setSelectionError(true);
                  return;
                }
                setSelectionError(false);
                onSelect(selected);
              },
            }}
          />
        )}
      </MapContainer>
      {selectionError && (
        <p className="field-error" role="alert">
          لا يمكن تحديد موقع خارج حدود سوريا. اختر موقعاً داخل الحدود.
        </p>
      )}
    </div>
  );
}
