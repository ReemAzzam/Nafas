import { describe, expect, it } from "vitest";
import {
  getSyriaCoordinatesError,
  isWithinSyria,
  parseSyriaCoordinates,
} from "./syriaLocation";

describe("Syria counselor location validation", () => {
  it("accepts Damascus and rejects locations outside the country polygon", () => {
    expect(isWithinSyria({ lat: 33.5138, lng: 36.2765 })).toBe(true);
    expect(isWithinSyria({ lat: 37.4, lng: 36 })).toBe(false);
    expect(isWithinSyria({ lat: 33.9, lng: 35.5 })).toBe(false);
  });

  it("rejects manually entered coordinates outside Syria", () => {
    expect(
      parseSyriaCoordinates({ lat: "37.4", lng: "36" }),
    ).toBeNull();
    expect(
      getSyriaCoordinatesError({ lat: "37.4", lng: "36" }),
    ).toBe("يجب أن يكون موقع المستشار داخل حدود سوريا.");
  });

  it("allows blank coordinates while a new location is being selected", () => {
    expect(parseSyriaCoordinates({ lat: "", lng: "" })).toBeNull();
    expect(getSyriaCoordinatesError({ lat: "", lng: "" })).toBeNull();
  });
});
